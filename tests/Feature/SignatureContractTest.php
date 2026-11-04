<?php

namespace Tests\Feature;

use App\Exceptions\ContractException;
use App\Mail\SignatureContractDecisionMail;
use App\Mail\SignatureContractReviewMail;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureContractParty;
use App\Models\SignatureContractVersion;
use App\Models\SignatureDocument;
use App\Models\User;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractDocxExporter;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Contratos (Assinaturas → Contratos): redigir em cláusulas, validar na
 * Gerência e gerar o documento de assinatura em rascunho.
 *
 * O que estes testes travam: numeração e remissão automáticas (a remissão
 * acompanha a cláusula quando a ordem muda), extenso, a lista de problemas
 * que trava o envio, o ciclo enviar → devolver → reenviar → aprovar com
 * versões e observações, o número único para todos os setores dado na
 * aprovação, o documento gerado SEM congelar, aditivo, reabertura e o
 * recorte por setor (só a Gerência vê todos e valida).
 */
class SignatureContractTest extends TestCase
{
    use CreatesContractSchema;
    use MocksSignatureUser;

    private Sector $compras;
    private Sector $rh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

        $this->compras = Sector::create(['name' => 'Compras']);
        $this->rh = Sector::create(['name' => 'RH']);

        $this->criaPresidente();

        // Destinatários e diretores consultam `users` (conexão mysql): trocados aqui.
        $destinatarios = \Mockery::mock(ContractRecipients::class);
        $destinatarios->shouldReceive('reviewers')->andReturn(['coordenador.gerencia@example.com']);
        $destinatarios->shouldReceive('author')->andReturn('compradora@example.com');
        $this->app->instance(ContractRecipients::class, $destinatarios);

        $diretores = \Mockery::mock(DirectorAreaService::class);
        $diretores->shouldReceive('options')->andReturn([]);
        $diretores->shouldReceive('signerData')->andReturnUsing(fn(int $area, int $user) => [
            'name' => 'Alex Ribeiro da Silva',
            'cpf' => '52998224725',
            'email' => 'presidente@example.com',
            'phone' => null,
            'member_id' => null,
            'user_id' => $user,
            'director_area_id' => $area,
            'director_area_name' => 'Presidência',
            'director_role' => 'primary',
        ]);
        $this->app->instance(DirectorAreaService::class, $diretores);
    }

    private function service(): ContractService
    {
        return app(ContractService::class);
    }

    private function renderer(): ContractRenderer
    {
        return app(ContractRenderer::class);
    }

    private function contrato(?Sector $setor = null, array $troca = []): SignatureContract
    {
        $contrato = $this->service()->create(($setor ?? $this->compras)->id, ($setor ?? $this->compras)->name, null, null, 7, 'Compradora');

        $contrato->forceFill(['content' => $this->conteudoCompleto($troca)])->save();

        return $contrato->fresh();
    }

    /** @param array<int, string> $permissoes */
    private function usuario(array $sectorIds, bool $gerencia = false, array $permissoes = ['assinatura.contratos']): User
    {
        $user = $this->usuarioComPermissoes($permissoes);
        $user->shouldReceive('sectorIds')->andReturn($sectorIds);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);

        return $user;
    }

    // ---------------------------------------------------------------- texto

    public function test_numera_clausulas_e_itens_e_escreve_o_extenso(): void
    {
        $html = $this->renderer()->html($this->conteudoCompleto(), null);

        $this->assertStringContainsString('CLÁUSULA PRIMEIRA – OBJETO', $html);
        $this->assertStringContainsString('CLÁUSULA TERCEIRA – MULTA', $html);
        $this->assertStringContainsString('<strong>1.1</strong> - Manutenção', $html);
        $this->assertStringContainsString('<strong>1.1.1</strong> - Pára-raios', $html);
        $this->assertStringContainsString('R$ 8.900,00 (oito mil e novecentos reais)', $html);
        $this->assertStringContainsString('10% (dez por cento)', $html);
        $this->assertStringContainsString('previsto no item 2.1', $html);
        $this->assertStringContainsString('A <strong>CONTRATADA</strong> é pessoa jurídica', $html);
        $this->assertStringContainsString('CONTRATO DE PRESTAÇÃO DE SERVIÇOS, QUE ENTRE SI CELEBRAM CLUBE DOS FUNCIONÁRIOS DA CSN E A.C.S TRADING LTDA.', $html);
        // O clube pelo CNPJ escolhido, representado pelo presidente.
        $this->assertStringContainsString('32.500.951/0005-40', $html);
        $this->assertStringContainsString('representado por seu Presidente, Alex Ribeiro da Silva', $html);
    }

    public function test_a_remissao_acompanha_a_clausula_quando_a_ordem_muda(): void
    {
        $conteudo = $this->conteudoCompleto();
        // A cláusula do Valor vai para o fim: o item citado passa de 2.1 para 3.1.
        $conteudo['clauses'] = [$conteudo['clauses'][0], $conteudo['clauses'][2], $conteudo['clauses'][1]];

        $html = $this->renderer()->html($conteudo, null);

        $this->assertStringContainsString('previsto no item 3.1', $html);
        $this->assertStringContainsString('CLÁUSULA SEGUNDA – MULTA', $html);
    }

    public function test_contrato_completo_nao_tem_erro(): void
    {
        $erros = collect($this->renderer()->problems($this->conteudoCompleto()))->where('level', 'error');

        $this->assertSame([], $erros->pluck('message')->all());
    }

    public function test_aponta_o_que_falta_e_o_que_parece_erro(): void
    {
        $conteudo = $this->conteudoCompleto([
            'clauses' => [
                ['uid' => 'clum000001', 'title' => 'Obrigações', 'items' => [
                    ['uid' => 'itum000011', 'level' => 1, 'html' => '<p>A CONTRADATA paga [[valor_inexistente]] conforme [[ref:naoexiste1]].</p>'],
                    ['uid' => 'itum000012', 'level' => 1, 'html' => '<p>Sujeita à cláusula penal do item 7.1.</p>'],
                ]],
            ],
        ]);
        $conteudo['parties'][0]['document'] = '11111111111111';

        $mensagens = collect($this->renderer()->problems($conteudo));
        $erros = $mensagens->where('level', 'error')->pluck('message')->join(' | ');
        $avisos = $mensagens->where('level', 'warning')->pluck('message')->join(' | ');

        $this->assertStringContainsString('[[valor_inexistente]]', $erros);
        $this->assertStringContainsString('Remissão no item 1.1 aponta para uma cláusula ou item que não existe mais', $erros);
        $this->assertStringContainsString('CNPJ de A.C.S TRADING LTDA inválido', $erros);
        $this->assertStringContainsString('"CONTRADATA"', $avisos);
        $this->assertStringContainsString('quis dizer CONTRATADA', $avisos);
        $this->assertStringContainsString('item 7.1', $avisos);
    }

    public function test_sem_presidente_o_contrato_nao_vai_a_gerencia(): void
    {
        \App\Models\SignatureDirectorAreaHolder::query()->update(['ended_at' => now()]);

        $erros = collect(app(ContractRenderer::class)->problems($this->conteudoCompleto()))->where('level', 'error');

        $this->assertTrue($erros->contains(fn($p) => str_contains($p['message'], 'Não há titular na área')));
    }

    // ---------------------------------------------------------------- ciclo

    public function test_envia_devolve_reenvia_e_aprova_gerando_o_documento_em_rascunho(): void
    {
        $contrato = $this->contrato();

        $v1 = $this->service()->submit($contrato, 'Primeira versão', 7, 'Compradora');

        $this->assertSame(1, $v1->version);
        $this->assertSame(SignatureContract::STATUS_IN_REVIEW, $contrato->fresh()->status);
        Mail::assertSent(SignatureContractReviewMail::class, fn($m) => $m->hasTo('coordenador.gerencia@example.com'));

        $this->service()->returnToAuthor($contrato->fresh(), 'Rever o valor', ['clvalor001' => 'O valor está errado.'], 9, 'Gerente');

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->status);
        $this->assertSame(SignatureContractVersion::DECISION_RETURNED, $contrato->latestVersion()->decision);
        $this->assertSame(['clvalor001', null], $contrato->comments()->pluck('clause_uid')->sortDesc()->values()->all());
        Mail::assertSent(SignatureContractDecisionMail::class, fn($m) => $m->hasTo('compradora@example.com'));

        $input = $this->conteudoCompleto();
        $input['variables'][0]['value'] = '9.500,00';
        $this->service()->save($contrato, $input, 7, 'Compradora');
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');

        $resultado = $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_APPROVED, $contrato->status);
        $this->assertSame('CTL - 1/' . now()->format('y'), $contrato->number);
        $this->assertSame(2, $contrato->current_version);

        /** @var SignatureDocument $documento */
        $documento = $resultado['document'];
        $this->assertNotNull($documento);
        $this->assertSame($documento->id, $contrato->signature_document_id);
        // Sem congelar (decisão do usuário).
        $this->assertSame(SignatureDocument::STATUS_DRAFT, $documento->status);
        $this->assertNull($documento->frozen_at);
        $this->assertTrue($documento->template->single_use);
        $this->assertTrue($documento->template->requires_initials);
        $this->assertStringContainsString('R$ 9.500,00 (nove mil e quinhentos reais)', $documento->template->body_html);
        $this->assertStringContainsString('[[data_da_assinatura]]', $documento->template->body_html);
        $this->assertSame(SignatureFieldTypes::DATE_SIGNING_LONG, $documento->template->variables[0]['type']);
        $this->assertStringContainsString('[[assinatura:p1]]', $documento->template->body_html);
        $this->assertStringStartsWith('CTL - 1/', $documento->title);

        // Presidente (diretor) + quem assina pela contratada + testemunha.
        $signers = $documento->signers()->orderBy('position')->get();
        $this->assertSame(['Alex Ribeiro da Silva', 'Maria Márcia Prazeres do Prado', 'Edalmo de Mendonça'], $signers->pluck('name')->all());
        $this->assertSame(50, $signers[0]->user_id);
        $this->assertSame('CONTRATANTE', $signers[0]->party_label);
        $this->assertSame('CONTRATADA — A.C.S TRADING LTDA', $signers[1]->party_label);
        $this->assertSame('Testemunha', $signers[2]->party_label);
    }

    public function test_nao_envia_com_problema_que_trava(): void
    {
        $contrato = $this->contrato(null, ['title' => '']);

        try {
            $this->service()->submit($contrato, null, 7, 'Compradora');
            $this->fail('Enviou contrato sem título.');
        } catch (ContractException $e) {
            $this->assertNotEmpty($e->problems);
        }

        $this->assertSame(SignatureContract::STATUS_DRAFT, $contrato->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_numero_e_uma_sequencia_so_para_todos_os_setores(): void
    {
        $a = $this->contrato($this->compras);
        $b = $this->contrato($this->rh);

        foreach ([$a, $b] as $c) {
            $this->service()->submit($c, null, 7, 'X');
            $this->service()->approve($c->fresh(), null, 9, 'Gerente');
        }

        $this->assertSame('CTL - 1/' . now()->format('y'), $a->fresh()->number);
        $this->assertSame('CTL - 2/' . now()->format('y'), $b->fresh()->number);
    }

    public function test_ajuste_da_gerencia_vira_versao_propria(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato, null, 7, 'Compradora');

        $input = $this->conteudoCompleto();
        $input['clauses'][0]['title'] = 'Do objeto';
        $this->service()->save($contrato->fresh(), $input, 9, 'Gerente');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $versoes = $contrato->fresh()->versions()->get();
        $this->assertSame([2, 1], $versoes->pluck('version')->all());
        $this->assertTrue($versoes[0]->by_reviewer);
        $this->assertSame(SignatureContractVersion::DECISION_APPROVED, $versoes[0]->decision);
        $this->assertSame(SignatureContractVersion::DECISION_SUPERSEDED, $versoes[1]->decision);
    }

    public function test_aditivo_herda_as_partes_e_ganha_numero_do_original(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato, null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $aditivo = $this->service()->createAddendum($contrato->fresh(), 7, 'Compradora');

        $this->assertSame('A.C.S TRADING LTDA', $aditivo->counterpartyName());
        $this->assertCount(1, $aditivo->content['clauses']);
        $this->assertSame($contrato->sector_id, $aditivo->sector_id);

        $conteudo = $aditivo->content;
        $conteudo['clauses'][0]['items'][0]['html'] = '<p>Prorroga o prazo por 30 dias.</p>';
        $this->service()->save($aditivo, $conteudo, 7, 'Compradora');
        $this->service()->submit($aditivo->fresh(), null, 7, 'Compradora');
        $this->service()->approve($aditivo->fresh(), null, 9, 'Gerente');

        $this->assertSame($contrato->fresh()->number . ' A1', $aditivo->fresh()->number);
    }

    public function test_reabrir_cancela_o_rascunho_do_documento(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato, null, 7, 'Compradora');
        $documento = $this->service()->approve($contrato->fresh(), null, 9, 'Gerente')['document'];

        $this->service()->reopen($contrato->fresh(), 7, 'Compradora');

        $this->assertSame(SignatureContract::STATUS_DRAFT, $contrato->fresh()->status);
        $this->assertSame(SignatureDocument::STATUS_CANCELED, $documento->fresh()->status);
        // O número fica: é o mesmo contrato.
        $this->assertNotNull($contrato->fresh()->number);
    }

    public function test_anexo_segue_para_o_documento_de_assinatura(): void
    {
        $contrato = $this->contrato();
        $this->service()->addAttachment($contrato, UploadedFile::fake()->createWithContent('proposta.pdf', "%PDF-1.4\n%%EOF\n"), 'Anexo I — Proposta', 7, 'Compradora');

        $this->service()->submit($contrato, null, 7, 'Compradora');
        $documento = $this->service()->approve($contrato->fresh(), null, 9, 'Gerente')['document'];

        $this->assertSame(['Anexo I — Proposta'], $documento->attachments()->pluck('label')->all());
    }

    public function test_guarda_a_contratada_para_o_proximo_contrato(): void
    {
        $contrato = $this->contrato();
        $this->service()->save($contrato, $this->conteudoCompleto(), 7, 'Compradora');

        $parte = SignatureContractParty::where('document', '04758575000129')->first();

        $this->assertSame('A.C.S TRADING LTDA', $parte?->name);
        $this->assertSame('Maria Márcia Prazeres do Prado', $parte->data['rep_name']);
    }

    public function test_exporta_word(): void
    {
        $bytes = app(ContractDocxExporter::class)->export($this->contrato());

        $arquivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($arquivo, $bytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($arquivo) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($arquivo);

        $this->assertStringContainsString('CLÁUSULA PRIMEIRA – OBJETO', $xml);
        $this->assertStringContainsString('oito mil e novecentos reais', $xml);
        $this->assertStringContainsString('1.1.1 - ', $xml);
    }

    /** @return array<string, string> as partes do .docx, por nome */
    private function partesDoWord(string $bytes): array
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($arquivo, $bytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($arquivo) === true);
        $partes = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = (string) $zip->getNameIndex($i);
            $partes[$nome] = (string) $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($arquivo);

        return $partes;
    }

    public function test_word_sai_no_papel_timbrado(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk(config('signature.disk'))->put('signature/layouts/cab.png', $png);
        Storage::disk(config('signature.disk'))->put('signature/layouts/rod.png', $png);
        \App\Models\SignatureLayout::create([
            'header_path' => 'signature/layouts/cab.png',
            'footer_path' => 'signature/layouts/rod.png',
            'header_height_mm' => 22,
            'footer_height_mm' => 16,
            'footer_text' => 'Rua 90, 1000 — Volta Redonda/RJ',
            'align' => 'center',
        ]);

        $partes = $this->partesDoWord(app(ContractDocxExporter::class)->export($this->contrato()));

        // Cabeçalho e rodapé ligados à seção, com as imagens dentro do pacote.
        $this->assertStringContainsString('<w:headerReference w:type="default" r:id="rIdheader"/>', $partes['word/document.xml']);
        $this->assertStringContainsString('<w:footerReference w:type="default" r:id="rIdfooter"/>', $partes['word/document.xml']);
        $this->assertStringContainsString('r:embed="rIdImgHeader"', $partes['word/header1.xml']);
        $this->assertStringContainsString('<w:jc w:val="center"/>', $partes['word/header1.xml']);
        $this->assertStringContainsString('Rua 90, 1000', $partes['word/footer1.xml']);
        $this->assertStringContainsString('r:embed="rIdImgFooter"', $partes['word/footer1.xml']);
        $this->assertSame($png, $partes['word/media/header-rIdImgHeader.png']);
        $this->assertStringContainsString('media/footer-rIdImgFooter.png', $partes['word/_rels/footer1.xml.rels']);
        $this->assertStringContainsString('Extension="png"', $partes['[Content_Types].xml']);

        // Todo XML do pacote abre.
        foreach ($partes as $nome => $conteudo) {
            if (str_ends_with($nome, '.xml') || str_ends_with($nome, '.rels')) {
                $this->assertNotFalse(@simplexml_load_string($conteudo), "XML inválido: {$nome}");
            }
        }
    }

    public function test_word_sem_papel_timbrado_leva_o_cabecalho_de_texto(): void
    {
        $partes = $this->partesDoWord(app(ContractDocxExporter::class)->export($this->contrato()));

        $this->assertStringContainsString('Clube dos Funcionários da CSN', $partes['word/header1.xml']);
        $this->assertArrayNotHasKey('word/footer1.xml', $partes);
        $this->assertNotFalse(@simplexml_load_string($partes['word/header1.xml']));
    }

    public function test_modelo_do_setor_nao_leva_partes_nem_valores(): void
    {
        $modelo = $this->service()->saveAsModel($this->contrato(), 'Prestação de serviços', 7, 'Compradora');

        $this->assertSame('', $modelo->content['parties'][0]['name']);
        $this->assertSame('', $modelo->content['variables'][0]['value']);
        $this->assertSame([], $modelo->content['signers']);

        $novo = $this->service()->create($this->compras->id, 'Compras', null, $modelo, 7, 'Compradora');

        $this->assertCount(3, $novo->content['clauses']);
        // Uid novo: o contrato do modelo não divide cláusula com o original.
        $this->assertNotSame('clobjeto01', $novo->content['clauses'][0]['uid']);
        // E a remissão interna foi junto para a cópia.
        $this->assertStringContainsString('[[ref:' . $novo->content['clauses'][1]['items'][0]['uid'] . ']]', $novo->content['clauses'][2]['items'][0]['html']);
    }

    // ---------------------------------------------------------------- acesso e telas

    public function test_cada_setor_so_ve_os_proprios_contratos(): void
    {
        $doRh = $this->contrato($this->rh);
        $deCompras = $this->contrato($this->compras);

        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.show', $doRh))->assertForbidden();
        $this->actingAs($compradora)->get(route('contracts.show', $deCompras))->assertOk()->assertSee('A.C.S TRADING LTDA');

        $this->actingAs($compradora)->get(route('contracts.index'))
            ->assertOk()
            ->assertSee(route('contracts.show', $deCompras))
            ->assertDontSee(route('contracts.show', $doRh));
    }

    public function test_so_a_gerencia_valida_e_ve_todos(): void
    {
        $contrato = $this->contrato($this->rh);
        $this->service()->submit($contrato, null, 7, 'X');

        $outroSetor = $this->usuario([$this->rh->id]);
        $this->actingAs($outroSetor)
            ->post(route('contracts.decide', $contrato), ['decision' => 'approve'])
            ->assertForbidden();

        $gerente = $this->usuario([999], true);
        $this->actingAs($gerente)->get(route('contracts.show', $contrato))->assertOk()->assertSee('Validação da Gerência');
        $this->actingAs($gerente)
            ->post(route('contracts.decide', $contrato), ['decision' => 'approve'] + $this->vistoWeb())
            ->assertRedirect(route('contracts.show', $contrato));

        $this->assertSame(SignatureContract::STATUS_APPROVED, $contrato->fresh()->status);
    }

    public function test_cria_pela_tela_no_proprio_setor(): void
    {
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.create'))->assertOk();

        $this->actingAs($compradora)
            ->post(route('contracts.store'), ['sector_id' => $this->rh->id])
            ->assertSessionHasErrors('sector_id');

        $resposta = $this->actingAs($compradora)->post(route('contracts.store'), [
            'sector_id' => $this->compras->id,
            'title' => 'Contrato de locação de imóvel',
            'party' => $this->contratadoDoPasso(),
        ]);

        $contrato = SignatureContract::latest('id')->first();
        // Abre no passo "Variáveis": o contratado já foi dito.
        $resposta->assertRedirect(route('contracts.edit', ['signatureContract' => $contrato, 'passo' => 1]));
        $this->assertSame('Contrato de locação de imóvel', $contrato->title);
        $this->assertSame('Compras', $contrato->sector_name);
    }

    /** Passo "Contratado" da criação (04/11/2026). */
    private function contratadoDoPasso(array $troca = []): array
    {
        return array_merge([
            'kind' => 'pj',
            'name' => 'ELLO SERVIÇOS ESPECIALIZADOS LTDA',
            'document' => '04.758.575/0001-29',
            'address' => 'Av. Dezessete de Julho, nº 29, Aterrado, Volta Redonda/RJ',
            'rep_name' => 'Alexandre Henrique de Azevedo',
            'rep_cpf' => '529.982.247-25',
            'email' => 'ello@example.com',
            'phone' => '(24) 99834-0467',
        ], $troca);
    }

    public function test_criar_pede_o_contratado_e_ja_o_poe_como_parte_e_signatario(): void
    {
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.create'))
            ->assertOk()
            ->assertSee('data-step="Contratado"', false);

        $this->actingAs($compradora)
            ->post(route('contracts.store'), ['sector_id' => $this->compras->id])
            ->assertSessionHasErrors(['party.kind', 'party.name', 'party.document', 'party.address']);

        $this->actingAs($compradora)
            ->post(route('contracts.store'), ['sector_id' => $this->compras->id, 'party' => $this->contratadoDoPasso(['document' => '11.111.111/1111-11', 'rep_cpf' => ''])])
            ->assertSessionHasErrors(['party.document', 'party.rep_cpf']);

        $this->actingAs($compradora)
            ->post(route('contracts.store'), ['sector_id' => $this->compras->id, 'party' => $this->contratadoDoPasso()])
            ->assertSessionHasNoErrors();

        $conteudo = SignatureContract::latest('id')->first()->content;
        $parte = $conteudo['parties'][0];

        $this->assertSame('ELLO SERVIÇOS ESPECIALIZADOS LTDA', $parte['name']);
        $this->assertSame('04758575000129', $parte['document']);
        $this->assertSame('CONTRATADA', $parte['term']);

        $assina = collect($conteudo['signers'])->firstWhere('party', 'contratada');
        $this->assertSame('Alexandre Henrique de Azevedo', $assina['name']);
        $this->assertSame('52998224725', $assina['cpf']);
        $this->assertSame('ello@example.com', $assina['email']);

        // Guardado no cadastro para o próximo contrato.
        $this->assertSame('ELLO SERVIÇOS ESPECIALIZADOS LTDA', SignatureContractParty::where('document', '04758575000129')->value('name'));

        // O editor em passos abre em "Variáveis" e, ao salvar, volta no passo em que estava.
        $contrato = SignatureContract::latest('id')->first();
        $this->actingAs($compradora)->get(route('contracts.edit', ['signatureContract' => $contrato, 'passo' => 1]))
            ->assertOk()
            ->assertSee('data-step-start="1"', false)
            ->assertSee('data-step="Variáveis"', false)
            ->assertSee('data-step="Quem assina"', false);

        $this->actingAs($compradora)
            ->put(route('contracts.update', $contrato), $contrato->content + ['passo' => 2])
            ->assertRedirect(route('contracts.edit', ['signatureContract' => $contrato, 'passo' => 2]));
    }

    public function test_contratado_pessoa_fisica_assina_ele_mesmo_e_o_termo_do_modelo_fica(): void
    {
        $compradora = $this->usuario([$this->compras->id]);

        $modelo = \App\Models\SignatureContractModel::create([
            'sector_id' => $this->compras->id,
            'sector_name' => 'Compras',
            'name' => 'Locação',
            'content' => ContractContent::normalize([
                'title' => 'Contrato de locação',
                'contractor_term' => 'LOCADOR',
                'parties' => [['role' => 'contratada', 'term' => 'LOCATÁRIA', 'kind' => 'pj']],
                'clauses' => [['title' => 'Objeto', 'items' => [['html' => 'Locação.']]]],
            ]),
        ]);

        $this->actingAs($compradora)->post(route('contracts.store'), [
            'sector_id' => $this->compras->id,
            'model_id' => $modelo->id,
            'party' => [
                'kind' => 'pf',
                'gender' => 'f',
                'name' => 'Denise Frizas Pires Grijó',
                'document' => '111.444.777-35',
                'address' => 'Rua 657, nº 118, Siderópolis, Volta Redonda/RJ',
                // Sobra de quando estava marcado "Empresa": ignorado na pessoa física.
                'rep_name' => 'X',
                'rep_cpf' => '123',
            ],
        ])->assertSessionHasNoErrors();

        $conteudo = SignatureContract::latest('id')->first()->content;

        $this->assertSame('LOCATÁRIA', $conteudo['parties'][0]['term']);
        $this->assertSame('pf', $conteudo['parties'][0]['kind']);
        $this->assertSame('', $conteudo['parties'][0]['rep_name']);
        $assina = collect($conteudo['signers'])->firstWhere('party', 'contratada');
        $this->assertSame('Denise Frizas Pires Grijó', $assina['name']);
        $this->assertSame('11144477735', $assina['cpf']);
    }

    public function test_editor_salva_e_mostra_a_previa(): void
    {
        $contrato = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.edit', $contrato))
            ->assertOk()
            ->assertSee('CLÁUSULA PRIMEIRA – OBJETO', false);

        $input = $this->conteudoCompleto();
        $input['clauses'][0]['title'] = 'Do objeto do contrato';

        $this->actingAs($compradora)
            ->put(route('contracts.update', $contrato), $input)
            ->assertRedirect(route('contracts.edit', $contrato));

        $this->assertSame('Do objeto do contrato', $contrato->fresh()->content['clauses'][0]['title']);

        $this->actingAs($compradora)
            ->postJson(route('contracts.preview', $contrato), $input)
            ->assertOk()
            ->assertJsonPath('problems', fn($p) => collect($p)->where('level', 'error')->isEmpty())
            ->assertJsonFragment(['uid' => 'itvalor011', 'number' => '2.1']);
    }

    public function test_texto_e_saneado(): void
    {
        $conteudo = ContractContent::normalize([
            'clauses' => [['title' => 'X', 'items' => [['html' => '<p onclick="x()">Oi<script>alert(1)</script></p>']]]],
        ]);

        $this->assertSame('<p>Oi</p>', $conteudo['clauses'][0]['items'][0]['html']);
    }

    // -------------------------------------- módulo Contratos (04/11/2026)

    /** Contrato aprovado, com o documento de assinatura em rascunho. */
    private function contratoAprovado(?Sector $setor = null): SignatureContract
    {
        $contrato = $this->contrato($setor);
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        return $contrato->fresh();
    }

    public function test_aba_assinaturas_de_contratos_lista_e_abre_o_documento_do_setor(): void
    {
        $contrato = $this->contratoAprovado();
        $documento = SignatureDocument::findOrFail($contrato->signature_document_id);
        // Só a permissão da aba: sem `assinatura.documentos`.
        $assina = $this->usuario([$this->compras->id], false, ['contratos.assinatura']);

        $this->actingAs($assina)->get(route('contract-signatures.index'))
            ->assertOk()
            ->assertSee('Assinaturas dos contratos')
            ->assertSee($documento->title)
            ->assertDontSee('Novo documento')
            ->assertSee(route('contract-signatures.show', $documento), false);

        // O mesmo processo de Assinaturas, com os links na aba de Contratos.
        $this->actingAs($assina)->get(route('contract-signatures.show', $documento))
            ->assertOk()
            ->assertSee(route('contract-signatures.freeze', $documento), false)
            ->assertDontSee(route('signature-documents.freeze', $documento), false);

        $this->assertTrue($assina->can('release', $documento));
        $this->assertTrue($assina->can('update', $documento));
        // Assinaturas segue fechado para ela.
        $this->actingAs($assina)->get(route('signature-documents.index'))->assertForbidden();
    }

    public function test_aba_assinaturas_nao_abre_contrato_de_outro_setor(): void
    {
        $contrato = $this->contratoAprovado();
        $documento = SignatureDocument::findOrFail($contrato->signature_document_id);
        $doRh = $this->usuario([$this->rh->id], false, ['contratos.assinatura']);

        $this->actingAs($doRh)->get(route('contract-signatures.index'))->assertOk()->assertDontSee($documento->title);
        $this->actingAs($doRh)->get(route('contract-signatures.show', $documento))->assertForbidden();
        $this->assertFalse($doRh->can('release', $documento));
    }

    public function test_aba_assinaturas_so_abre_documento_de_contrato(): void
    {
        $contrato = $this->contratoAprovado();
        $documento = SignatureDocument::findOrFail($contrato->signature_document_id);
        // Desligado do contrato, é um documento qualquer de Assinaturas.
        $contrato->forceFill(['signature_document_id' => null])->save();

        $atendente = $this->usuario([$this->compras->id], false, ['assinatura.documentos', 'contratos.assinatura']);

        $this->actingAs($atendente)->get(route('contract-signatures.show', $documento))->assertNotFound();
    }

    public function test_endereco_antigo_de_contratos_redireciona(): void
    {
        $contrato = $this->contrato();
        $user = $this->usuario([$this->compras->id]);

        $this->actingAs($user)->get('/assinatura/contratos/' . $contrato->id . '?aba=x')
            ->assertStatus(301)
            ->assertRedirect('/contratos/' . $contrato->id . '?aba=x');
        $this->actingAs($user)->get('/assinatura/contratos')->assertRedirect('/contratos');
        $this->assertSame(url('/contratos/' . $contrato->id), route('contracts.show', $contrato));
    }

    /** O visto como o formulário do computador manda: traços em JSON. */
    private function vistoWeb(): array
    {
        $visto = $this->vistoDeTeste();

        return ['initials' => $visto['initials'], 'initials_strokes' => json_encode($visto['initials_strokes'])];
    }

    public function test_aprovar_exige_o_visto_da_gerencia(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato, null, 7, 'Compradora');
        $gerente = $this->usuario([999], true);

        $this->actingAs($gerente)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('data-initials-pad', false);

        // Sem visto, ou com um risco só, não aprova.
        $this->actingAs($gerente)
            ->post(route('contracts.decide', $contrato), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHas('warning', fn($m) => str_contains($m, 'visto'));
        $this->actingAs($gerente)
            ->post(route('contracts.decide', $contrato), ['decision' => 'approve', 'initials' => $this->vistoWeb()['initials'], 'initials_strokes' => '[[{"x":1,"y":1}]]'])
            ->assertSessionHas('warning');
        $this->assertSame(SignatureContract::STATUS_IN_REVIEW, $contrato->fresh()->status);

        // Devolver não pede visto.
        $this->assertSame([], Storage::disk(config('signature.disk'))->allFiles('signature/contracts/' . $contrato->id));
    }

    public function test_visto_da_gerencia_vai_para_o_documento_em_todas_as_paginas(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato, null, 7, 'Compradora');

        $this->actingAs($this->usuario([999], true))
            ->post(route('contracts.decide', $contrato), ['decision' => 'approve'] + $this->vistoWeb())
            ->assertSessionHas('success');

        $contrato = $contrato->fresh();
        $versao = SignatureContractVersion::find($contrato->approved_version_id);
        $documento = SignatureDocument::findOrFail($contrato->signature_document_id);

        $this->assertNotNull($versao->approval_initials_path);
        Storage::disk(config('signature.disk'))->assertExists($versao->approval_initials_path);
        $this->assertSame($versao->approval_initials_path, $documento->approval_initials_path);
        $this->assertNotSame('', (string) $documento->approval_initials_label, 'o nome de quem aprovou');

        // A faixa do visto existe por causa dele, mesmo sem o visto dos signatários (gov.br).
        $renderer = app(\App\Services\Signature\SignatureDocumentRenderer::class);
        $this->assertTrue($renderer->geometry($documento)->initials);
        $documento->forceFill(['initials_waived_at' => now()])->save();
        $this->assertFalse($documento->fresh()->requiresInitials());
        $this->assertTrue($renderer->geometry($documento->fresh())->initials);

        // O PDF sai (o visto é desenhado em cada página) e o manifesto registra.
        $this->assertStringStartsWith('%PDF', $renderer->pdf($documento->fresh()));
        $manifesto = (new \ReflectionMethod($renderer, 'manifest'))->invoke($renderer, $documento->fresh());
        $this->assertStringContainsString('Visto da validação do contrato', $manifesto);
    }

    public function test_biblioteca_so_consulta_sem_a_permissao_da_biblioteca(): void
    {
        // Desde 04/11/2026, cadastrar/editar/excluir pede `contratos.biblioteca`.
        $redatora = $this->usuario([$this->compras->id]);

        $this->actingAs($redatora)->postJson(route('contract-library.store'), [
            'sector_id' => $this->compras->id,
            'title' => 'Foro',
            'items' => [['html' => '<p>Foro.</p>', 'level' => 1]],
        ])->assertForbidden();

        $this->actingAs($redatora)->get(route('contract-library.index'))
            ->assertOk()
            ->assertSee('permissão da biblioteca')
            ->assertDontSee('id="libForm"', false);

        // Quem só cuida da biblioteca também a abre.
        $curadora = $this->usuario([$this->compras->id], false, ['contratos.biblioteca']);
        $this->actingAs($curadora)->get(route('contract-library.index'))->assertOk()->assertSee('id="libForm"', false);
    }

    public function test_biblioteca_guarda_e_lista_por_setor(): void
    {
        $compradora = $this->usuario([$this->compras->id], false, ['assinatura.contratos', 'contratos.biblioteca']);

        $this->actingAs($compradora)->postJson(route('contract-library.store'), [
            'sector_id' => $this->compras->id,
            'title' => 'Foro',
            'category' => 'Foro',
            'items' => [['html' => '<p>Fica eleito o Foro de Volta Redonda.</p>', 'level' => 1]],
        ])->assertOk();

        $this->actingAs($compradora)->postJson(route('contract-library.store'), [
            'sector_id' => $this->rh->id,
            'title' => 'De outro setor',
            'items' => [['html' => '<p>x</p>']],
        ])->assertStatus(422);

        $this->actingAs($compradora)->get(route('contract-library.index'))->assertOk()->assertSee('Fica eleito o Foro');

        $contrato = $this->contrato();
        $this->actingAs($compradora)->getJson(route('contracts.library', $contrato))
            ->assertOk()
            ->assertJsonPath('0.title', 'Foro');
    }
}
