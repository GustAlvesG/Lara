<?php

namespace Tests\Feature;

use App\Exceptions\ContractConflictException;
use App\Mail\SignatureContractRefusedMail;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureContractParty;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Contracts\ContractVariables;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\SignatureCaptureService;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Contratos — revisões pedidas na apresentação do módulo (05/11/2026):
 *
 *  - variáveis: "Preenchimento", tipo pelo nome, onde cada uma é usada e o
 *    [[marcador]] que ainda não é variável;
 *  - salvar automático com aviso de "alguém salvou antes de você";
 *  - Prestadores: cadastro próprio, documento único, inativar tira da busca;
 *  - recusa na assinatura: presidente ou contratada devolvem o contrato
 *    para revisão (testemunha só encerra o documento); o presidente recusa
 *    também pela tela do contrato;
 *  - o andamento da assinatura na lista de Contratos (aba "Em assinatura").
 */
class SignatureContractRevisionsTest extends TestCase
{
    use CreatesContractSchema;
    use MocksSignatureUser;

    private Sector $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

        $this->compras = Sector::create(['name' => 'Compras']);
        $this->criaPresidente();

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

    private function contrato(array $troca = []): SignatureContract
    {
        $contrato = $this->service()->create($this->compras->id, $this->compras->name, null, null, 7, 'Compradora');
        $contrato->forceFill(['content' => $this->conteudoCompleto($troca)])->save();

        return $contrato->fresh();
    }

    /** Aprovado e com o documento congelado (colhendo assinaturas). */
    private function contratoEmAssinatura(): SignatureContract
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $contrato = $contrato->fresh();
        app(SignatureDocumentService::class)->freeze($contrato->document);

        return $contrato->fresh();
    }

    /** Recusa como no tablet/link: liberação emitida, consumida e recusada. */
    private function recusaNoTablet(SignatureSigner $signer, string $motivo): void
    {
        $pedidos = app(SignatureRequestService::class);
        // O diretor abre pela Assinatura da Diretoria (matrícula e PIN), não pelo QR.
        $emitida = $pedidos->issue($signer, 9, 'Atendente', $signer->isDirector() ? \App\Models\SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN : null);
        $sessao = $pedidos->consumeIssued($emitida['request'], '127.0.0.1', 'phpunit', 'teste');

        app(SignatureCaptureService::class)->refuse($sessao['request'], $motivo, ['ip' => '127.0.0.1']);
    }

    /** @param array<int, string> $permissoes */
    private function usuario(array $sectorIds, bool $gerencia = false, array $permissoes = ['assinatura.contratos'], int $id = 1): User
    {
        $user = $this->usuarioComPermissoes($permissoes);
        $user->shouldReceive('sectorIds')->andReturn($sectorIds);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);
        $user->id = $id;

        return $user;
    }

    // ------------------------------------------------------------ variáveis

    public function test_tipo_da_variavel_sai_do_nome(): void
    {
        $this->assertSame(ContractVariables::MONEY, ContractVariables::guessType('Valor global'));
        $this->assertSame(ContractVariables::DATE_LONG, ContractVariables::guessType('Data de início'));
        $this->assertSame(ContractVariables::NUMBER, ContractVariables::guessType('Prazo de vigência em meses'));
        $this->assertSame(ContractVariables::PERCENT, ContractVariables::guessType('Multa em percentual'));
        $this->assertSame(ContractVariables::CPF, ContractVariables::guessType('CPF do responsável'));
        $this->assertSame(ContractVariables::TEXT, ContractVariables::guessType('Local da prestação do serviço'));

        // As variáveis comuns já vêm com o tipo coerente com o palpite (menos as de texto livre).
        foreach (ContractVariables::COMMON as $comum) {
            $this->assertTrue(ContractVariables::exists($comum['type']), $comum['label']);
        }
    }

    public function test_diz_onde_cada_variavel_e_usada_e_acha_o_marcador_sem_variavel(): void
    {
        $conteudo = $this->conteudoCompleto();
        $conteudo['clauses'][0]['items'][0]['html'] = '<p>Vigência de [[Prazo de vigência]] meses, a partir de [[data_de_inicio]].</p>';

        $uso = app(ContractRenderer::class)->variableUsage($conteudo);

        $this->assertSame(['Cláusula 2'], $uso['usage']['valor_global']);
        $this->assertSame(['Cláusula 3'], $uso['usage']['multa']);
        $this->assertEqualsCanonicalizing(['prazo_de_vigencia', 'data_de_inicio'], array_column($uso['unknown'], 'key'));

        $prazo = collect($uso['unknown'])->firstWhere('key', 'prazo_de_vigencia');
        $this->assertSame('Prazo de vigência', $prazo['label']);
        $this->assertSame(ContractVariables::NUMBER, $prazo['type']);

        $data = collect($uso['unknown'])->firstWhere('key', 'data_de_inicio');
        $this->assertSame('Data de inicio', $data['label']);
        $this->assertSame(ContractVariables::DATE_LONG, $data['type']);
    }

    public function test_editor_fala_em_preenchimento_e_tem_as_abas(): void
    {
        $contrato = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.edit', $contrato))
            ->assertOk()
            ->assertSee('Nome e tipo da variável')
            ->assertSee('Variável comum…')
            ->assertSee('data-side-tab="previa"', false)
            ->assertSee('data-side-tab="conferencia"', false)
            ->assertSee('Usada em: Cláusula 2.');
    }

    /**
     * 09/11/2026: à vista só o preenchimento, com o nome da variável como rótulo; nome
     * e tipo recolhidos em "Nome e tipo da variável". O campo segue o tipo: calendário
     * para data (a guardada como 10/11/2026 vai como 2026-11-10), "R$" e "%" ao lado.
     */
    public function test_variavel_mostra_so_o_preenchimento_e_o_campo_segue_o_tipo(): void
    {
        $contrato = $this->contrato(['variables' => [
            ['label' => 'Valor global', 'type' => 'money', 'value' => '8.900,00'],
            ['label' => 'Multa', 'type' => 'percent', 'value' => '10'],
            ['label' => 'Data de início', 'type' => 'date_long', 'value' => '10/11/2026'],
        ]]);

        $html = $this->actingAs($this->usuario([$this->compras->id]))
            ->get(route('contracts.edit', $contrato))
            ->assertOk()
            ->assertSee('<span data-var-title>Valor global</span>', false)
            ->assertSee('data-var-prefix>R$</span>', false)
            ->assertSee('data-var-suffix>%</span>', false)
            ->assertSee('value="2026-11-10"', false)
            ->assertSee('Sai por extenso: 28 de setembro de 2026.')
            ->getContent();

        // Variáveis salvas: o "Nome e tipo" vem fechado; o campo de data é o calendário.
        // (o único aberto é o da linha modelo, a variável nova, conferida abaixo).
        $this->assertSame(1, preg_match_all('/<details[^>]*data-var-settings[^>]*\bopen\b/', $html));
        $this->assertMatchesRegularExpression('/<input type="date"[^>]*value="2026-11-10"/', $html);
        // A linha modelo (variável nova, sem nome) abre com o "Nome e tipo" aberto.
        $this->assertMatchesRegularExpression('/<template id="tpl-variable">.*?<details[^>]*data-var-settings[^>]*\bopen\b/s', $html);
    }

    /** O calendário manda 2026-11-10; o contrato guarda 10/11/2026, como sempre. */
    public function test_data_do_calendario_e_guardada_no_formato_de_sempre(): void
    {
        $contrato = $this->contrato();
        $input = $this->conteudoCompleto();
        $input['variables'][] = ['key' => '', 'label' => 'Data de início', 'type' => 'date_long', 'value' => '2026-11-10'];

        $this->actingAs($this->usuario([$this->compras->id]))
            ->putJson(route('contracts.autosave', $contrato), $input + ['revision' => 0])
            ->assertOk();

        $data = collect($contrato->fresh()->content['variables'])->firstWhere('label', 'Data de início');
        $this->assertSame('10/11/2026', $data['value']);
        $this->assertSame(['10/11/2026', null], [ContractVariables::storedValue('date', '2026-11-10'), null]);
        $this->assertSame('2026-11-10', ContractVariables::inputValue('date_long', '10/11/2026'));
        // Texto não muda de formato.
        $this->assertSame('2026-11-10', ContractVariables::storedValue('text', '2026-11-10'));
    }

    // ---------------------------------------------------- salvar automático

    public function test_salvar_automatico_grava_e_sobe_a_revisao(): void
    {
        $contrato = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);
        $input = $this->conteudoCompleto();
        $input['clauses'][0]['title'] = 'Do objeto';
        $input['variables'][] = ['key' => '', 'label' => 'Prazo de vigência', 'type' => 'number', 'value' => '12'];

        $this->actingAs($compradora)
            ->putJson(route('contracts.autosave', $contrato), $input + ['revision' => 0])
            ->assertOk()
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('variables.2', 'prazo_de_vigencia');

        $contrato = $contrato->fresh();
        $this->assertSame('Do objeto', $contrato->content['clauses'][0]['title']);
        $this->assertSame(1, $contrato->content_revision);
        $this->assertSame('Atendente de Teste', $contrato->content_saved_by_name);
    }

    public function test_alguem_salvou_antes_avisa_em_vez_de_gravar_por_cima(): void
    {
        $contrato = $this->contrato();
        // Outra pessoa gravou: a revisão já é 1.
        $this->service()->save($contrato, $this->conteudoCompleto(['title' => 'Versão da Ana']), 8, 'Ana');

        $compradora = $this->usuario([$this->compras->id]);
        $meu = $this->conteudoCompleto(['title' => 'Minha versão']);

        $this->actingAs($compradora)
            ->putJson(route('contracts.autosave', $contrato), $meu + ['revision' => 0])
            ->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('saved_by', 'Ana');

        $this->assertSame('Versão da Ana', $contrato->fresh()->content['title']);

        // A pessoa escolheu gravar por cima.
        $this->actingAs($compradora)
            ->putJson(route('contracts.autosave', $contrato), $meu + ['revision' => 0, 'force' => 1])
            ->assertOk();

        $this->assertSame('Minha versão', $contrato->fresh()->content['title']);

        // O "Salvar" do formulário também confere.
        $this->expectException(ContractConflictException::class);
        $this->service()->save($contrato->fresh(), $meu, 7, 'Compradora', 0);
    }

    public function test_salvar_automatico_de_contrato_que_saiu_da_redacao_pede_para_recarregar(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');

        $this->actingAs($this->usuario([$this->compras->id]))
            ->putJson(route('contracts.autosave', $contrato), $this->conteudoCompleto() + ['revision' => 0])
            ->assertStatus(409)
            ->assertJsonPath('reload', true);
    }

    // ----------------------------------------------------------- prestadores

    public function test_cadastra_prestador_sem_contrato_e_o_editor_acha(): void
    {
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contract-providers.index'))->assertOk()->assertSee('Prestadores');

        $this->actingAs($compradora)->post(route('contract-providers.store'), [
            'kind' => 'pj',
            'name' => 'Limpeza Brilho Ltda',
            'document' => '11.222.333/0001-81',
            'address' => 'Rua A, 10, Volta Redonda/RJ',
            'rep_name' => 'João da Silva',
            'rep_cpf' => '529.982.247-25',
            'email' => 'Contato@Brilho.example.com',
        ])->assertRedirect(route('contract-providers.index'));

        $prestador = SignatureContractParty::firstWhere('document', '11222333000181');
        $this->assertNotNull($prestador);
        $this->assertTrue($prestador->active);
        $this->assertSame('52998224725', $prestador->field('rep_cpf'));
        $this->assertSame('contato@brilho.example.com', $prestador->field('email'));
        $this->assertSame('Atendente de Teste', $prestador->created_by_name);

        $this->actingAs($compradora)->getJson(route('contracts.parties', ['q' => 'Brilho']))
            ->assertOk()
            ->assertJsonPath('0.name', 'Limpeza Brilho Ltda')
            ->assertJsonPath('0.rep_name', 'João da Silva');

        // Documento repetido não entra.
        $this->actingAs($compradora)->post(route('contract-providers.store'), [
            'kind' => 'pj', 'name' => 'Outra', 'document' => '11222333000181', 'address' => 'X',
            'rep_name' => 'Y', 'rep_cpf' => '52998224725',
        ])->assertSessionHasErrors('document');
    }

    public function test_prestador_inativo_some_da_busca_e_salvar_contrato_nao_reativa(): void
    {
        $compradora = $this->usuario([$this->compras->id]);
        $contrato = $this->contrato();
        // Salvar o contrato guarda a contratada no cadastro.
        $this->service()->save($contrato, $this->conteudoCompleto(), 7, 'Compradora');
        $prestador = SignatureContractParty::firstWhere('document', '04758575000129');

        $this->actingAs($compradora)->post(route('contract-providers.toggle', $prestador))->assertRedirect();
        $this->assertFalse($prestador->fresh()->active);

        $this->actingAs($compradora)->getJson(route('contracts.parties', ['q' => 'TRADING']))->assertOk()->assertJsonCount(0);

        $this->service()->save($contrato->fresh(), $this->conteudoCompleto(), 7, 'Compradora');
        $this->assertFalse($prestador->fresh()->active);

        // A edição mostra os contratos que usam o prestador.
        $this->actingAs($compradora)->get(route('contract-providers.edit', $prestador))
            ->assertOk()
            ->assertSee(route('contracts.show', $contrato));
    }

    public function test_prestadores_e_de_quem_acessa_contratos(): void
    {
        $semAcesso = $this->usuario([$this->compras->id], false, []);

        $this->actingAs($semAcesso)->get(route('contract-providers.index'))->assertForbidden();
        $this->actingAs($semAcesso)->post(route('contract-providers.store'), ['kind' => 'pj'])->assertForbidden();
    }

    // ------------------------------------------------ recusa na assinatura

    public function test_recusa_do_presidente_no_tablet_devolve_o_contrato(): void
    {
        $contrato = $this->contratoEmAssinatura();
        $documento = $contrato->document;
        $presidente = $documento->signers()->where('user_id', 50)->firstOrFail();
        // O diretor só assina depois de quem é de fora da diretoria: os outros já assinaram.
        $documento->signers()->where('id', '!=', $presidente->id)->update(['status' => SignatureSigner::STATUS_SIGNED, 'signed_at' => now()]);

        $this->recusaNoTablet($presidente->fresh(), 'A cláusula de multa precisa de prazo.');

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->status);
        $this->assertNull($contrato->signature_document_id);
        $this->assertSame('CTL - 1/' . now()->format('y'), $contrato->number);
        $this->assertSame(SignatureDocument::STATUS_REFUSED, $documento->fresh()->status);

        $observacao = $contrato->comments()->firstOrFail();
        $this->assertNull($observacao->clause_uid);
        $this->assertStringContainsString('presidente', $observacao->body);
        $this->assertStringContainsString('A cláusula de multa precisa de prazo.', $observacao->body);
        $this->assertSame('signature_refused', $contrato->events()->latest('id')->first()->event);
        Mail::assertSent(SignatureContractRefusedMail::class, fn($m) => $m->hasTo('compradora@example.com'));

        // Ajustado e aprovado de novo: documento NOVO, assinaturas do zero, mesmo número.
        $this->service()->submit($contrato, null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $contrato = $contrato->fresh();
        $this->assertNotSame($documento->id, $contrato->signature_document_id);
        $this->assertSame('CTL - 1/' . now()->format('y'), $contrato->number);
        $this->assertSame(0, $contrato->document->signers()->where('status', SignatureSigner::STATUS_SIGNED)->count());
    }

    public function test_recusa_da_contratada_tambem_devolve(): void
    {
        $contrato = $this->contratoEmAssinatura();
        $contratada = $contrato->document->signers()->where('name', 'Maria Márcia Prazeres do Prado')->firstOrFail();

        $this->recusaNoTablet($contratada, 'O valor combinado foi outro.');

        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->fresh()->status);
        $this->assertStringContainsString('contratada', $contrato->fresh()->comments()->firstOrFail()->body);
    }

    public function test_recusa_da_testemunha_so_encerra_o_documento(): void
    {
        $contrato = $this->contratoEmAssinatura();
        $testemunha = $contrato->document->signers()->where('name', 'Edalmo de Mendonça')->firstOrFail();

        $this->recusaNoTablet($testemunha, 'Não posso testemunhar.');

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_APPROVED, $contrato->status);
        $this->assertSame(SignatureDocument::STATUS_REFUSED, $contrato->document->status);
        Mail::assertNotSent(SignatureContractRefusedMail::class);

        // Dá para gerar outro documento.
        $resultado = $this->service()->generateDocument($contrato, 7, 'Compradora');
        $this->assertNotNull($resultado['document']);
        $this->assertSame(SignatureDocument::STATUS_DRAFT, $resultado['document']->status);
    }

    public function test_presidente_recusa_pela_tela_do_contrato(): void
    {
        $contrato = $this->contratoEmAssinatura();
        $documento = $contrato->document;
        // O presidente não é do setor nem da Gerência, e não tem a permissão de Contratos.
        $presidente = $this->usuario([], false, [], 50);
        $outro = $this->usuario([], false, [], 51);

        $this->actingAs($outro)->get(route('contracts.show', $contrato))->assertForbidden();

        $this->actingAs($presidente)->get(route('contracts.index'))->assertOk()->assertSee(route('contracts.show', $contrato));
        $this->actingAs($presidente)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Aguardando a sua assinatura')
            ->assertDontSee('Reabrir para alteração');

        // Sem motivo, não recusa.
        $this->actingAs($presidente)->post(route('contracts.refuse-signature', $contrato), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($presidente)
            ->post(route('contracts.refuse-signature', $contrato), ['reason' => 'Prazo de vigência deve ser de 6 meses.'])
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->fresh()->status);
        $this->assertSame(SignatureDocument::STATUS_REFUSED, $documento->fresh()->status);
        $this->assertSame('Prazo de vigência deve ser de 6 meses.', $documento->signers()->where('user_id', 50)->first()->refusal_reason);

        // Devolvido, ele não vê mais (não aguarda a assinatura dele).
        $this->actingAs($presidente)->get(route('contracts.show', $contrato))->assertForbidden();
    }

    public function test_presidente_recusa_o_rascunho_ainda_nao_congelado(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        $documento = $contrato->fresh()->document;

        $this->service()->refuseByPresident($contrato->fresh(), 'Rever o objeto.', 50, 'Alex Ribeiro da Silva');

        $this->assertSame(SignatureDocument::STATUS_CANCELED, $documento->fresh()->status);
        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->fresh()->status);
    }

    // ------------------------------------------- andamento em Contratos

    public function test_aba_em_assinatura_mostra_o_andamento(): void
    {
        $emAssinatura = $this->contratoEmAssinatura();
        $rascunho = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.index', ['aba' => 'assinatura']))
            ->assertOk()
            ->assertSee(route('contracts.show', $emAssinatura))
            ->assertDontSee(route('contracts.show', $rascunho))
            ->assertSee('0/3')
            ->assertSee('Falta: Alex Ribeiro da Silva (CONTRATANTE); Maria Márcia Prazeres do Prado', false);

        $this->actingAs($compradora)->get(route('contracts.show', $emAssinatura))
            ->assertOk()
            ->assertSee('0 de 3 assinaram');
    }
}
