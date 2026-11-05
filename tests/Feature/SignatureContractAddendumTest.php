<?php

namespace Tests\Feature;

use App\Exceptions\ContractException;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureDocument;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\SignatureDocumentRenderer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Contratos — cabeçalho fixo e aditivos (05/11/2026, decisões do usuário):
 *
 *  - cabeçalho "CONTRATO DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE …
 *    E <PRESTADOR>"; aditivo "1º TERMO ADITIVO AO CONTRATO CTL - …"; o PDF
 *    não repete o título "CTL — título — contratada";
 *  - aditivo só de contrato assinado, pelo botão e pelo Novo contrato;
 *  - herda partes, CNPJ e variáveis da base (último aditivo assinado ou o
 *    principal) e só quem assina pela contratada;
 *  - [[original:chave]] no texto;
 *  - marcar/desmarcar como aditivo antes da aprovação, só completando.
 */
class SignatureContractAddendumTest extends TestCase
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

    /** Aprova e dá o documento por assinado (finalizado). */
    private function assina(SignatureContract $contrato): SignatureContract
    {
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        $contrato->fresh()->document->forceFill(['status' => SignatureDocument::STATUS_FINALIZED])->save();

        return $contrato->fresh();
    }

    private function usuario(array $sectorIds, bool $gerencia = false, array $permissoes = ['assinatura.contratos'])
    {
        $user = $this->usuarioComPermissoes($permissoes);
        $user->shouldReceive('sectorIds')->andReturn($sectorIds);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);

        return $user;
    }

    /** O aditivo pronto para enviar: cláusula escrita. */
    private function escreve(SignatureContract $aditivo, string $html): SignatureContract
    {
        $conteudo = $aditivo->content;
        $conteudo['clauses'][0]['items'][0]['html'] = $html;
        $conteudo['signers'][] = ['party' => 'testemunha', 'source' => 'person', 'name' => 'Edalmo de Mendonça', 'cpf' => '111.444.777-35'];

        return $this->service()->save($aditivo->fresh(), $conteudo, 7, 'Compradora')->fresh();
    }

    // ------------------------------------------------------------- cabeçalho

    public function test_cabecalho_fixo_com_todas_as_contratadas(): void
    {
        $conteudo = $this->conteudoCompleto(['title' => 'Contrato teste geral com contabilidade']);
        $conteudo['parties'][] = [
            'role' => 'contratada', 'term' => 'CONTRATADA', 'kind' => 'pj', 'name' => 'Gustavo Alves Serviços',
            'document' => '11.222.333/0001-81', 'address' => 'Rua B', 'rep_name' => 'Gustavo', 'rep_cpf' => '529.982.247-25',
        ];
        $conteudo['parties'][] = [
            'role' => 'fiador', 'term' => 'FIADOR', 'kind' => 'pf', 'name' => 'Fulano Fiador', 'document' => '529.982.247-25', 'address' => 'Rua C',
        ];

        $html = app(ContractRenderer::class)->html(\App\Services\Signature\Contracts\ContractContent::normalize($conteudo), 'CTL - 1/26');

        $this->assertStringContainsString(
            'CONTRATO DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E A.C.S TRADING LTDA E GUSTAVO ALVES SERVIÇOS.',
            $html,
        );
        $this->assertStringNotContainsString('CONTRATO TESTE GERAL', $html);
        $this->assertStringNotContainsString('FULANO FIADOR', $html);
        // O número continua logo abaixo.
        $this->assertStringContainsString('<strong>CTL - 1/26</strong>', $html);
    }

    public function test_pdf_do_contrato_nao_repete_o_titulo(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        $documento = $contrato->fresh()->document;

        $html = app(SignatureDocumentRenderer::class)->html($documento, SignatureDocumentRenderer::MODE_ORIGINAL, false);

        $this->assertStringNotContainsString('<h1>' . e($documento->title) . '</h1>', $html);
        $this->assertStringNotContainsString('<div class="doc">', $html);
        $this->assertStringContainsString('QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E A.C.S TRADING LTDA.', $html);
        // O título continua sendo o nome do documento nas listas.
        $this->assertStringStartsWith('CTL - 1/', $documento->title);
    }

    public function test_assinaturas_so_com_nome_em_maiusculas_e_cargo(): void
    {
        $conteudo = $this->conteudoCompleto();
        $conteudo['signers'][0]['caption'] = 'Sócia-administradora';

        $previa = app(ContractRenderer::class)->html($conteudo, null);
        $assinaturas = substr($previa, strpos($previa, 'class="ct-sigs"'));

        $this->assertStringContainsString('<strong>ALEX RIBEIRO DA SILVA</strong><br><span>' . config('signature.contracts.president_area') . '</span>', $assinaturas);
        $this->assertStringContainsString('<strong>MARIA MÁRCIA PRAZERES DO PRADO</strong><br><span>Sócia-administradora</span>', $assinaturas);
        $this->assertStringContainsString('<strong>EDALMO DE MENDONÇA</strong></div>', $assinaturas);
        $this->assertStringNotContainsString('CONTRATANTE', $assinaturas);
        $this->assertStringNotContainsString('<span>Testemunha</span>', $assinaturas);

        // No PDF do documento de assinatura, o mesmo: diretor com o cargo da Diretoria.
        $contrato = $this->contrato(['signers' => $conteudo['signers']]);
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        $pdf = app(SignatureDocumentRenderer::class)->html($contrato->fresh()->document, SignatureDocumentRenderer::MODE_ORIGINAL, false);

        $this->assertStringContainsString('<div class="sig-name">ALEX RIBEIRO DA SILVA</div>', $pdf);
        $this->assertStringContainsString('Presidência — CPF', $pdf);
        $this->assertStringContainsString('<div class="sig-name">MARIA MÁRCIA PRAZERES DO PRADO</div>', $pdf);
        $this->assertStringContainsString('Sócia-administradora — CPF', $pdf);
        $this->assertStringNotContainsString('CONTRATADA — A.C.S TRADING LTDA — CPF', $pdf);
    }

    public function test_documentos_sem_titular_ou_substituto(): void
    {
        // O editor gravava "Fulano (Titular)" a partir do texto da lista de diretores.
        $this->assertSame('Jose Fausto Ferreira Junior', \App\Services\Signature\Contracts\ContractContent::directorName('Jose Fausto Ferreira Junior (Titular)'));
        $this->assertSame('Ana Souza', \App\Services\Signature\Contracts\ContractContent::directorName('Ana Souza (substituto)'));
        $this->assertSame('Ana Souza', \App\Services\Signature\Contracts\ContractContent::directorName('Ana Souza (Suplente)'));

        $conteudo = $this->conteudoCompleto();
        $conteudo['signers'][] = ['uid' => 'dirx', 'party' => 'contratante', 'source' => 'director', 'area_id' => 777, 'user_id' => 777,
            'name' => 'Jose Fausto Ferreira Junior (Titular)', 'cpf' => '', 'email' => '', 'phone' => '', 'caption' => ''];
        $previa = app(ContractRenderer::class)->html($conteudo, null);
        $this->assertStringContainsString('JOSE FAUSTO FERREIRA JUNIOR</strong>', $previa);
        $this->assertStringNotContainsString('TITULAR', $previa);

        // No PDF, o substituto sai só com a área; nas telas, o papel continua.
        $s = new \App\Models\SignatureSigner(['director_area_name' => 'Diretoria de Patrimônio', 'director_role' => \App\Models\SignatureDirectorAreaHolder::ROLE_SUBSTITUTE]);
        $s->director_area_id = 1;
        $s->user_id = 1;
        $this->assertSame('Diretoria de Patrimônio', $s->documentCapacityLabel());
        $this->assertSame('Diretoria de Patrimônio (substituto)', $s->capacityLabel());
    }

    // --------------------------------------------------------------- aditivo

    public function test_aditivo_so_de_contrato_assinado(): void
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');

        $this->expectException(ContractException::class);
        $this->service()->createAddendum($contrato->fresh(), 7, 'Compradora');
    }

    public function test_aditivo_herda_partes_variaveis_e_so_a_contratada_e_tem_cabecalho_proprio(): void
    {
        $contrato = $this->assina($this->contrato());

        $aditivo = $this->service()->createAddendum($contrato, 7, 'Compradora');
        $conteudo = $aditivo->content;

        $this->assertSame(SignatureContract::KIND_ADDENDUM, $aditivo->kind);
        $this->assertSame($contrato->id, $aditivo->parent_id);
        $this->assertSame('A.C.S TRADING LTDA', $aditivo->counterpartyName());
        $this->assertSame(['valor_global', 'multa'], array_column($conteudo['variables'], 'key'));
        // Só quem assina pela contratada: a testemunha do contrato não vem.
        $this->assertSame(['contratada'], array_column($conteudo['signers'], 'party'));

        $html = app(ContractRenderer::class)->html($conteudo, null);
        $this->assertStringContainsString(
            '1º TERMO ADITIVO AO CONTRATO ' . $contrato->number . ' DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E A.C.S TRADING LTDA.',
            $html,
        );

        // [[original:…]] usa a variável do contrato.
        $aditivo = $this->escreve($aditivo, '<p>O valor de [[original:valor_global]] passa a [[valor_global]].</p>');
        $this->assertSame([], collect(app(ContractRenderer::class)->problems($aditivo->content))->where('level', 'error')->values()->all());
        $this->assertStringContainsString('O valor de R$ 8.900,00 (oito mil e novecentos reais) passa a', app(ContractRenderer::class)->html($aditivo->content, null));

        $aditivo = $this->assina($aditivo);
        $this->assertSame($contrato->number . ' A1', $aditivo->number);
        $this->assertStringContainsString('1º TERMO ADITIVO AO CONTRATO', $aditivo->document->template->body_html);
    }

    public function test_variaveis_vem_do_ultimo_aditivo_assinado(): void
    {
        $contrato = $this->assina($this->contrato());

        $primeiro = $this->service()->createAddendum($contrato, 7, 'Compradora');
        $conteudo = $primeiro->content;
        $conteudo['variables'][0]['value'] = '9.500,00';
        $this->service()->save($primeiro, $conteudo, 7, 'Compradora');
        $primeiro = $this->assina($this->escreve($primeiro, '<p>Reajuste.</p>'));

        // Aditivo do aditivo é aditivo do principal; a base é o 1º aditivo (assinado).
        $segundo = $this->service()->createAddendum($primeiro, 7, 'Compradora');

        $this->assertSame($contrato->id, $segundo->parent_id);
        $this->assertSame('9.500,00', $segundo->content['variables'][0]['value']);
        $this->assertSame($primeiro->number, $segundo->content['addendum']['base_number']);
        $this->assertSame(2, $segundo->content['addendum']['ordinal']);

        $segundo = $this->escreve($segundo, '<p>Antes: [[original:valor_global]].</p>');
        $this->assertStringContainsString('Antes: R$ 9.500,00', app(ContractRenderer::class)->html($segundo->content, null));
        $this->assertStringContainsString('2º TERMO ADITIVO AO CONTRATO ' . $contrato->number, app(ContractRenderer::class)->html($segundo->content, null));
    }

    public function test_original_fora_de_aditivo_e_problema(): void
    {
        $conteudo = $this->conteudoCompleto();
        $conteudo['clauses'][0]['items'][0]['html'] = '<p>Valor anterior: [[original:valor_global]].</p>';

        $erros = collect(app(ContractRenderer::class)->problems($conteudo))->where('level', 'error')->pluck('message')->join(' ');

        $this->assertStringContainsString('só aditivo usa variável do contrato original', $erros);
    }

    public function test_novo_contrato_como_aditivo_so_lista_assinados(): void
    {
        $assinado = $this->assina($this->contrato());
        $rascunho = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.create'))
            ->assertOk()
            ->assertSee('Aditivo de contrato assinado')
            ->assertSee('value="' . $assinado->id . '"', false)
            ->assertDontSee('value="' . $rascunho->id . '"', false);

        $this->actingAs($compradora)->post(route('contracts.store'), ['kind' => 'addendum', 'parent_id' => $rascunho->id])
            ->assertSessionHasErrors('parent_id');

        $resposta = $this->actingAs($compradora)->post(route('contracts.store'), ['kind' => 'addendum', 'parent_id' => $assinado->id]);

        $aditivo = SignatureContract::where('parent_id', $assinado->id)->firstOrFail();
        $resposta->assertRedirect(route('contracts.edit', ['signatureContract' => $aditivo, 'passo' => 2]));
        $this->assertSame('A.C.S TRADING LTDA', $aditivo->counterpartyName());
    }

    public function test_marcar_rascunho_como_aditivo_so_completa_e_desmarcar(): void
    {
        $assinado = $this->assina($this->contrato());

        // Rascunho com variável própria (mesma chave, outro preenchimento) e sem contratada.
        $rascunho = $this->service()->create($this->compras->id, 'Compras', 'Aditivo de prazo', null, 7, 'Compradora');
        $conteudo = $rascunho->content;
        $conteudo['variables'] = [['label' => 'Valor global', 'type' => 'money', 'value' => '1.000,00']];
        $conteudo['clauses'][0]['items'][0]['html'] = '<p>Meu texto.</p>';
        $this->service()->save($rascunho, $conteudo, 7, 'Compradora');

        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.show', $rascunho))->assertOk()->assertSee('Marcar como aditivo');

        $this->actingAs($compradora)->post(route('contracts.link-addendum', $rascunho), ['parent_id' => $assinado->id])->assertRedirect();

        $rascunho = $rascunho->fresh();
        $this->assertSame(SignatureContract::KIND_ADDENDUM, $rascunho->kind);
        $this->assertSame($assinado->id, $rascunho->parent_id);
        // Só completou: o preenchimento dele ficou, a variável que faltava entrou.
        $this->assertSame('1.000,00', collect($rascunho->content['variables'])->firstWhere('key', 'valor_global')['value']);
        $this->assertNotNull(collect($rascunho->content['variables'])->firstWhere('key', 'multa'));
        $this->assertSame('<p>Meu texto.</p>', $rascunho->content['clauses'][0]['items'][0]['html']);
        $this->assertSame('A.C.S TRADING LTDA', $rascunho->counterpartyName());
        $this->assertSame('Maria Márcia Prazeres do Prado', collect($rascunho->content['signers'])->firstWhere('party', 'contratada')['name']);

        $this->actingAs($compradora)->post(route('contracts.unlink-addendum', $rascunho))->assertRedirect();

        $rascunho = $rascunho->fresh();
        $this->assertSame(SignatureContract::KIND_CONTRACT, $rascunho->kind);
        $this->assertNull($rascunho->parent_id);
        $this->assertNull($rascunho->content['addendum']);
    }

    public function test_nao_muda_o_tipo_depois_de_aprovado(): void
    {
        $assinado = $this->assina($this->contrato());
        $outro = $this->contrato();
        $this->service()->submit($outro->fresh(), null, 7, 'Compradora');
        $this->service()->approve($outro->fresh(), null, 9, 'Gerente');

        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.link-addendum', $outro), ['parent_id' => $assinado->id])
            ->assertForbidden();
    }
}
