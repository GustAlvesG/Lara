<?php

namespace Tests\Feature;

use App\Exceptions\ClaudeException;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureContractAiReview;
use App\Models\User;
use App\Services\Claude\ClaudeClient;
use App\Services\Signature\Contracts\ContractAiReviewer;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Revisão automática de contratos pelo Claude (API da Anthropic).
 *
 * O que estes testes travam: o pedido que sai para a API (modelo, chave,
 * formato JSON por esquema, o contrato inteiro no texto), a tradução dos
 * erros da API em mensagem para a tela, a revisão guardada e reaproveitada
 * quando o texto não mudou, e que só a Gerência pede e vê.
 *
 * Nenhum teste fala com a API de verdade: o HTTP é um Guzzle com respostas
 * prontas, ou o ClaudeClient é trocado por um mock.
 */
class SignatureContractAiReviewTest extends TestCase
{
    use CreatesContractSchema;
    use MocksSignatureUser;

    private Sector $compras;

    /** @var array<int, array<string, mixed>> */
    private array $historico = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

        config([
            'claude.api_key' => 'sk-ant-teste',
            'claude.contract_review.model' => 'claude-haiku-5-5',
        ]);

        $this->compras = Sector::create(['name' => 'Compras']);
        $this->criaPresidente();

        $destinatarios = \Mockery::mock(ContractRecipients::class);
        $destinatarios->shouldReceive('reviewers')->andReturn([]);
        $destinatarios->shouldReceive('author')->andReturn(null);
        $this->app->instance(ContractRecipients::class, $destinatarios);

        $diretores = \Mockery::mock(DirectorAreaService::class);
        $diretores->shouldReceive('options')->andReturn([]);
        $this->app->instance(DirectorAreaService::class, $diretores);
    }

    private function contrato(): SignatureContract
    {
        $contrato = app(ContractService::class)->create($this->compras->id, 'Compras', null, null, 7, 'Compradora');
        $contrato->forceFill(['content' => $this->conteudoCompleto()])->save();

        return $contrato->fresh();
    }

    private function usuario(bool $gerencia): User
    {
        $user = $this->usuarioComPermissoes(['assinatura.contratos']);
        $user->shouldReceive('sectorIds')->andReturn([$this->compras->id]);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);

        return $user;
    }

    /** O ClaudeClient de verdade, sobre um HTTP falso que grava o que foi enviado. */
    private function clienteComRespostas(Response ...$respostas): ClaudeClient
    {
        $pilha = HandlerStack::create(new MockHandler($respostas));
        $pilha->push(Middleware::history($this->historico));

        return new ClaudeClient(new Guzzle(['handler' => $pilha]));
    }

    private function respostaDaApi(array $dados, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_teste',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($dados)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 300],
        ]));
    }

    private function revisaoPronta(): array
    {
        return [
            'veredito' => 'ajustes_recomendados',
            'resumo' => 'O valor por extenso confere, mas falta o foro.',
            'apontamentos' => [
                ['clausula' => 2, 'trecho' => 'Item 2.1', 'gravidade' => 'media', 'problema' => 'Sem data de pagamento.', 'sugestao' => 'Incluir o dia do pagamento.'],
                ['clausula' => 99, 'trecho' => 'Geral', 'gravidade' => 'qualquer', 'problema' => 'Falta a cláusula de foro.', 'sugestao' => 'Eleger o foro da comarca.'],
                ['clausula' => 1, 'trecho' => '', 'gravidade' => 'baixa', 'problema' => '', 'sugestao' => 'vazio: some'],
            ],
        ];
    }

    // ---------------------------------------------------------------- cliente

    public function test_cliente_envia_o_pedido_no_formato_da_api_e_le_o_json(): void
    {
        $cliente = $this->clienteComRespostas($this->respostaDaApi(['ok' => true]));

        $resposta = $cliente->structured(
            model: 'claude-haiku-5-5',
            system: 'sistema',
            prompt: 'pergunta',
            schema: ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false],
            maxTokens: 1000,
            effort: 'medium',
        );

        $this->assertSame(['ok' => true], $resposta['data']);
        $this->assertSame(1200, $resposta['input_tokens']);
        $this->assertSame(300, $resposta['output_tokens']);

        $pedido = $this->historico[0]['request'];
        $corpo = json_decode((string) $pedido->getBody(), true);

        $this->assertStringEndsWith('/v1/messages', (string) $pedido->getUri());
        // O SDK manda a chave em `x-api-key` ou `Authorization: Bearer`, conforme a versão.
        $this->assertStringContainsString('sk-ant-teste', $pedido->getHeaderLine('x-api-key') . $pedido->getHeaderLine('Authorization'));
        $this->assertNotSame('', $pedido->getHeaderLine('anthropic-version'));
        $this->assertSame('claude-haiku-5-5', $corpo['model']);
        $this->assertSame(1000, $corpo['max_tokens']);
        $this->assertSame('json_schema', $corpo['output_config']['format']['type']);
        $this->assertSame('medium', $corpo['output_config']['effort']);
        $this->assertSame('pergunta', $corpo['messages'][0]['content']);
    }

    public function test_cliente_traduz_os_erros_da_api(): void
    {
        $erro = fn(int $status, string $tipo) => new Response($status, ['Content-Type' => 'application/json'],
            json_encode(['type' => 'error', 'error' => ['type' => $tipo, 'message' => 'x']]));

        $casos = [
            [$erro(401, 'authentication_error'), 'chave da API do Claude foi recusada'],
            [$this->respostaDaApi([], 'refusal'), 'não quis analisar'],
            [$this->respostaDaApi([], 'max_tokens'), 'veio cortada'],
        ];

        foreach ($casos as [$resposta, $esperado]) {
            try {
                $this->clienteComRespostas($resposta)->structured('claude-haiku-5-5', 's', 'p', ['type' => 'object'], 100);
                $this->fail('Deveria ter recusado: ' . $esperado);
            } catch (ClaudeException $e) {
                $this->assertStringContainsString($esperado, $e->getMessage());
            }
        }
    }

    public function test_sem_chave_nao_chama_a_api(): void
    {
        config(['claude.api_key' => null]);

        $this->expectException(ClaudeException::class);
        $this->clienteComRespostas()->structured('claude-haiku-5-5', 's', 'p', ['type' => 'object'], 100);
    }

    // ---------------------------------------------------------------- revisão

    public function test_manda_o_contrato_inteiro_e_guarda_a_revisao_limpa(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas($this->respostaDaApi($this->revisaoPronta())));
        $contrato = $this->contrato();

        $resultado = app(ContractAiReviewer::class)->review($contrato, 9, 'Gerente');
        $review = $resultado['review'];

        $this->assertFalse($resultado['reused']);
        $this->assertSame(SignatureContractAiReview::VERDICT_ADJUST, $review->verdict);
        $this->assertSame('O valor por extenso confere, mas falta o foro.', $review->summary);
        $this->assertCount(2, $review->findings, 'o apontamento sem problema é descartado');
        $this->assertSame(2, $review->findings[0]['clausula']);
        $this->assertSame(0, $review->findings[1]['clausula'], 'cláusula que não existe vira geral');
        $this->assertSame('media', $review->findings[1]['gravidade'], 'gravidade desconhecida vira média');
        $this->assertSame(1200, $review->input_tokens);
        $this->assertSame('Gerente', $review->requested_by_name);
        $this->assertTrue($contrato->events()->where('event', 'ai_reviewed')->exists());

        // O que foi para a API: o texto como na prévia, sem os trechos sensíveis.
        $corpo = json_decode((string) $this->historico[0]['request']->getBody(), true);
        $prompt = $corpo['messages'][0]['content'];
        $this->assertStringContainsString('CLÁUSULA PRIMEIRA – OBJETO', $prompt);
        $this->assertStringContainsString('1.1 Manutenção preventiva', $prompt);
        $this->assertStringContainsString('CLÁUSULA SEGUNDA – VALOR', $prompt, 'o título da cláusula retida segue');
        $this->assertStringContainsString('[não enviado: contém valores]', $prompt);
        foreach (['529.982.247-25', '04.758.575/0001-29', 'Rua Vereador', 'oito mil e novecentos reais', 'R$'] as $sensivel) {
            $this->assertStringNotContainsString($sensivel, $prompt);
        }
        $this->assertStringNotContainsString('<p>', $prompt);
        $this->assertSame('claude-haiku-5-5', $corpo['model']);

        $retidos = array_column($review->withheld, 'reasons', 'key');
        $this->assertSame(['valores'], $retidos['clausula-clvalor001']);
        $this->assertArrayNotHasKey('clausula-clobjeto01', $retidos);
        $this->assertContains('Qualificação – CONTRATADA', array_column($review->withheld, 'label'));
    }

    public function test_trecho_sensivel_vai_quando_o_gerente_marca(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas(
            $this->respostaDaApi($this->revisaoPronta()),
            $this->respostaDaApi($this->revisaoPronta()),
        ));
        $revisor = app(ContractAiReviewer::class);
        $contrato = $this->contrato();

        $todos = array_column($revisor->sensitiveSections($contrato), 'key');
        $this->assertContains('clausula-clvalor001', $todos);

        $tudo = $revisor->review($contrato, 9, 'Gerente', $todos);
        $prompt = json_decode((string) $this->historico[0]['request']->getBody(), true)['messages'][0]['content'];

        $this->assertSame([], $tudo['review']->withheld);
        $this->assertStringContainsString('2.1 O valor global', $prompt);
        $this->assertStringContainsString('oito mil e novecentos reais', $prompt);
        $this->assertStringContainsString('04.758.575/0001-29', $prompt);
        $this->assertStringNotContainsString('[não enviado', $prompt);

        // Mesma escolha reaproveita; outra escolha é outra análise.
        $this->assertTrue($revisor->review($contrato->fresh(), 9, 'Gerente', array_reverse($todos))['reused']);
        $so = $revisor->review($contrato->fresh(), 9, 'Gerente', ['clausula-clvalor001']);
        $this->assertFalse($so['reused']);
        $this->assertNotContains('clausula-clvalor001', $so['review']->withheldKeys());
        $this->assertCount(2, $this->historico);
    }

    public function test_trechos_anexados_vao_como_referencia_e_ficam_na_revisao(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas(
            $this->respostaDaApi($this->revisaoPronta()),
            $this->respostaDaApi($this->revisaoPronta()),
        ));
        $revisor = app(ContractAiReviewer::class);
        $contrato = $this->contrato();

        $extras = [
            ['title' => 'Proposta comercial', 'text' => "Prazo de 12 meses.\r\nReajuste pelo IPCA."],
            ['title' => '', 'text' => 'Garantia de 90 dias.'],
            ['title' => 'Vazio', 'text' => '   '],
        ];

        $primeira = $revisor->review($contrato, 9, 'Gerente', [], $extras);
        $prompt = json_decode((string) $this->historico[0]['request']->getBody(), true)['messages'][0]['content'];

        // Depois da minuta, marcados como material de referência; o vazio cai.
        $this->assertStringContainsString("<material titulo=\"Proposta comercial\">\nPrazo de 12 meses.\nReajuste pelo IPCA.\n</material>", $prompt);
        $this->assertStringContainsString('<material titulo="Trecho 2">', $prompt);
        $this->assertStringNotContainsString('Vazio', $prompt);
        $this->assertGreaterThan(strpos($prompt, '</minuta>'), strpos($prompt, '<material'));
        $this->assertSame(['Proposta comercial', 'Trecho 2'], array_column($primeira['review']->extraParts(), 'title'));

        // Os mesmos trechos reaproveitam; sem eles, é outro pedido.
        $this->assertTrue($revisor->review($contrato->fresh(), 9, 'Gerente', [], $extras)['reused']);
        $sem = $revisor->review($contrato->fresh(), 9, 'Gerente');
        $this->assertFalse($sem['reused']);
        $this->assertSame([], $sem['review']->extraParts());
        $this->assertCount(2, $this->historico);
    }

    public function test_trechos_anexados_pela_tela_do_contrato(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas($this->respostaDaApi($this->revisaoPronta())));
        $contrato = $this->contrato();

        $this->actingAs($this->usuario(false))
            ->post(route('contracts.ai-review', $contrato), [
                'extras' => [['title' => 'Contrato anterior', 'text' => 'Multa de 10%.']],
            ])
            ->assertRedirect();

        $this->assertSame('Contrato anterior', $contrato->aiReviews()->first()->extraParts()[0]['title']);

        $this->actingAs($this->usuario(false))
            ->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Trechos anexados ao pedido')
            ->assertSee('name="extras[0][text]"', false);
    }

    public function test_mesmo_texto_reaproveita_a_revisao_e_texto_novo_pede_outra(): void
    {
        $claude = \Mockery::mock(ClaudeClient::class);
        $claude->shouldReceive('isConfigured')->andReturn(true);
        $claude->shouldReceive('structured')->twice()->andReturn([
            'data' => $this->revisaoPronta(), 'model' => 'claude-haiku-5-5', 'input_tokens' => 1, 'output_tokens' => 1,
        ]);
        $this->app->instance(ClaudeClient::class, $claude);

        $revisor = app(ContractAiReviewer::class);
        $contrato = $this->contrato();

        $primeira = $revisor->review($contrato, 9, 'Gerente')['review'];
        $denovo = $revisor->review($contrato->fresh(), 9, 'Gerente');

        $this->assertTrue($denovo['reused']);
        $this->assertSame($primeira->id, $denovo['review']->id);
        $this->assertTrue($revisor->isCurrent($primeira, $contrato->fresh()));

        $conteudo = $contrato->content;
        $conteudo['clauses'][0]['title'] = 'Do objeto';
        $contrato->forceFill(['content' => $conteudo])->save();

        $this->assertFalse($revisor->isCurrent($primeira, $contrato->fresh()));
        $this->assertFalse($revisor->review($contrato->fresh(), 9, 'Gerente')['reused']);
        $this->assertSame(2, $contrato->aiReviews()->count());
    }

    // ---------------------------------------------------------------- tela

    public function test_quem_redige_pede_durante_a_redacao_e_salva_antes_de_analisar(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas($this->respostaDaApi($this->revisaoPronta())));
        $contrato = $this->contrato();
        $compradora = $this->usuario(false);

        $this->actingAs($compradora)->get(route('contracts.edit', $contrato))
            ->assertOk()
            ->assertSee('Revisão automática (Claude)')
            ->assertSee('Salvar e analisar')
            ->assertSee('form="ctForm" name="include[]"', false)
            ->assertDontSee('Levar para a observação');

        $conteudo = $this->conteudoCompleto(['title' => 'Título que ainda não estava salvo']);

        $this->actingAs($compradora)
            ->put(route('contracts.update', $contrato), $conteudo + ['next' => 'ai-review'])
            ->assertRedirect(route('contracts.edit', $contrato) . '#revisao-ia')
            ->assertSessionHas('success');

        // Analisou o texto que acabou de ser salvo, sem os trechos sensíveis não marcados.
        $prompt = json_decode((string) $this->historico[0]['request']->getBody(), true)['messages'][0]['content'];
        $this->assertStringContainsString('Título que ainda não estava salvo', $prompt);
        $this->assertStringNotContainsString('529.982.247-25', $prompt);
        $this->assertSame('Título que ainda não estava salvo', $contrato->fresh()->content['title']);

        $this->actingAs($compradora)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Falta a cláusula de foro.')
            ->assertDontSee('Levar para a observação');
    }

    public function test_analises_seguidas_pelo_editor_tem_limite(): void
    {
        $contrato = $this->contrato();
        $compradora = $this->usuario(false);
        RateLimiter::increment('contract-ai-review:' . $compradora->id, 60, 6);

        $this->actingAs($compradora)
            ->put(route('contracts.update', $contrato), $this->conteudoCompleto() + ['next' => 'ai-review'])
            ->assertRedirect(route('contracts.edit', $contrato))
            ->assertSessionHas('warning');

        $this->assertSame(0, $contrato->aiReviews()->count());
    }

    public function test_em_validacao_quem_redige_ve_e_so_a_gerencia_pede(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas($this->respostaDaApi($this->revisaoPronta())));
        $contrato = $this->contrato();
        app(ContractService::class)->submit($contrato, null, 7, 'Compradora');

        $compradora = $this->usuario(false);
        $this->actingAs($compradora)->post(route('contracts.ai-review', $contrato))->assertForbidden();
        $this->actingAs($compradora)->get(route('contracts.show', $contrato))
            ->assertOk()->assertDontSee('Analisar com o Claude');

        $gerente = $this->usuario(true);
        $this->actingAs($gerente)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Analisar com o Claude')
            ->assertSee('value="clausula-clvalor001"', false)
            ->assertSee('Cláusula 2 – Valor');

        $this->actingAs($gerente)->post(route('contracts.ai-review', $contrato), ['include' => ['clausula-clvalor001']])
            ->assertRedirect(route('contracts.show', $contrato) . '#revisao-ia');

        $prompt = json_decode((string) $this->historico[0]['request']->getBody(), true)['messages'][0]['content'];
        $this->assertStringContainsString('oito mil e novecentos reais', $prompt);
        $this->assertStringNotContainsString('529.982.247-25', $prompt);

        $this->actingAs($gerente)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Ajustes recomendados')
            ->assertSee('Falta a cláusula de foro.')
            ->assertSee('Levar para a observação da cláusula')
            ->assertSee('Não enviados ao Claude')
            ->assertSee('Analisar de novo escolhendo outros trechos')
            ->assertDontSee('Analisar com o Claude');
    }

    /** A resposta da Gerência, com a parte jurídica. */
    private function revisaoComJuridico(string $risco, bool $procurar = false): array
    {
        return $this->revisaoPronta() + ['juridico' => [
            'risco' => $risco,
            'resumo' => 'A multa só pesa contra o clube.',
            'fraquezas' => [
                ['clausula' => 3, 'ponto' => 'Multa sem contrapartida para a contratada.', 'sugestao' => 'Prever multa recíproca.'],
                ['clausula' => 0, 'ponto' => '', 'sugestao' => 'vazio: some'],
            ],
            'procurar_juridico' => $procurar,
        ]];
    }

    public function test_gerencia_recebe_a_revisao_juridica_e_risco_alto_orienta_procurar_o_juridico(): void
    {
        // O modelo diz "não precisa", mas o risco é alto: o Lara orienta mesmo assim.
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas($this->respostaDaApi($this->revisaoComJuridico('alto', false))));
        $contrato = $this->contrato();
        app(ContractService::class)->submit($contrato, null, 7, 'Compradora');
        $gerente = $this->usuario(true);

        $this->actingAs($gerente)->post(route('contracts.ai-review', $contrato))->assertRedirect();

        $corpo = json_encode(json_decode((string) $this->historico[0]['request']->getBody(), true), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Revisão jurídica (pedida pela Gerência)', $corpo);
        $this->assertStringContainsString('procurar_juridico', $corpo);

        $revisao = $contrato->aiReviews()->first();
        $this->assertSame('alto', $revisao->legalReview()['risk']);
        $this->assertTrue($revisao->seeksLawyer());
        $this->assertCount(1, $revisao->legalReview()['weaknesses'], 'fraqueza sem ponto é descartada');
        $this->assertSame(3, $revisao->legalReview()['weaknesses'][0]['clausula']);

        $this->actingAs($gerente)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Revisão jurídica')
            ->assertSee('Risco jurídico alto')
            ->assertSee('Procure um jurídico especializado antes de aprovar este contrato.')
            ->assertSee('Multa sem contrapartida para a contratada.')
            ->assertSee('não é parecer jurídico');
    }

    public function test_quem_redige_nao_recebe_a_parte_juridica_e_a_gerencia_pede_de_novo(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas(
            $this->respostaDaApi($this->revisaoPronta()),
            $this->respostaDaApi($this->revisaoComJuridico('medio')),
        ));
        $revisor = app(ContractAiReviewer::class);
        $contrato = $this->contrato();

        $sem = $revisor->review($contrato, 7, 'Compradora');
        $corpo = json_encode(json_decode((string) $this->historico[0]['request']->getBody(), true), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('procurar_juridico', $corpo);
        $this->assertNull($sem['review']->legalReview());

        // O mesmo texto, agora pela Gerência: a revisão sem a parte jurídica não serve.
        $com = $revisor->review($contrato->fresh(), 9, 'Gerente', [], [], true);
        $this->assertFalse($com['reused']);
        $this->assertSame('medio', $com['review']->legalReview()['risk']);
        $this->assertFalse($com['review']->seeksLawyer());

        // E a de quem redige, depois, pode reaproveitar a que já tem tudo.
        $this->assertTrue($revisor->review($contrato->fresh(), 7, 'Compradora')['reused']);
        $this->assertCount(2, $this->historico);
    }

    public function test_sem_chave_a_revisao_some_da_tela(): void
    {
        config(['claude.api_key' => '']);
        $contrato = $this->contrato();
        $gerente = $this->usuario(true);

        $this->actingAs($gerente)->get(route('contracts.show', $contrato))
            ->assertOk()->assertDontSee('Revisão automática (Claude)');

        $this->actingAs($gerente)->post(route('contracts.ai-review', $contrato))
            ->assertSessionHas('error');
    }

    public function test_erro_da_api_volta_como_mensagem(): void
    {
        $this->app->instance(ClaudeClient::class, $this->clienteComRespostas(
            new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'x']])),
            new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'x']])),
        ));
        config(['claude.max_retries' => 0]);

        $contrato = $this->contrato();

        $this->actingAs($this->usuario(true))->post(route('contracts.ai-review', $contrato))
            ->assertRedirect(route('contracts.show', $contrato))
            ->assertSessionHas('error', 'O Claude está indisponível no momento. Tente de novo em alguns minutos.');

        $this->assertSame(0, $contrato->aiReviews()->count());
    }
}
