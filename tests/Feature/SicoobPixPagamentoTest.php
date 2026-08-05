<?php

namespace Tests\Feature;

use App\Exceptions\Sicoob\SicoobAuthenticationException;
use App\Exceptions\Sicoob\SicoobInsufficientFundsException;
use App\Exceptions\Sicoob\SicoobPayeeMismatchException;
use App\Exceptions\Sicoob\SicoobPaymentOutcomeUnknownException;
use App\Exceptions\Sicoob\SicoobPaymentRejectedException;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\PixPayment;
use App\Services\Sicoob\SicoobPixPagamentoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Fluxo de envio de Pix contra um Sicoob simulado.
 *
 * NENHUM teste aqui faz chamada real — `Http::preventStrayRequests()` garante
 * isso: qualquer URL não declarada no fake derruba o teste em vez de sair pela
 * rede. Num fluxo que move dinheiro, "esqueci de mockar" não pode ser um erro
 * silencioso.
 *
 * O que estes testes protegem, acima de tudo, é a diferença entre os desfechos:
 * recusado (nada saiu, pode refazer) e desconhecido (pode ter saído, não
 * refaça). É nela que mora a possibilidade de pagar duas vezes.
 */
class SicoobPixPagamentoTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    private const TOKEN_URL = 'https://auth.sicoob.test/token';
    private const PIX_URL = 'https://api.sicoob.test/pix-pagamentos/v2';
    private const CC_URL = 'https://api.sicoob.test/conta-corrente/v4';

    private const E2E_ID = 'E7565769220260804120000abcdefgh';
    private const E2E_ID_2 = 'E7565769220260804130000ijklmnop';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        // Qualquer URL fora do fake derruba o teste em vez de sair pela rede.
        // Num fluxo que move dinheiro, "esqueci de mockar" não pode passar batido.
        Http::preventStrayRequests();

        config([
            'sicoob.enabled' => true,
            'sicoob.environment' => 'sandbox',
            'sicoob.client_id' => 'client-de-teste',
            'sicoob.token_url' => self::TOKEN_URL,
            'sicoob.pix.base_url' => self::PIX_URL,
            'sicoob.pix.max_amount' => 5000,
            'sicoob.pix.validar_titular' => true,
            'sicoob.conta_corrente.enabled' => true,
            'sicoob.conta_corrente.base_url' => self::CC_URL,
            // Um certificado real não existe na suíte; o arquivo só precisa ser
            // legível para o serviço não abortar antes de montar a requisição.
            'sicoob.certificate.path' => $this->fakeCertificate(),
            'sicoob.certificate.key_path' => $this->fakeCertificate(),
            'sicoob.certificate.key_password' => null,
        ]);

        Cache::flush();
    }

    /* =====================================================================
     | Caminho feliz
     |=====================================================================*/

    public function test_envia_pix_em_dois_passos_e_finaliza(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $payment = $this->pixPayment(250.00);

        $resultado = app(SicoobPixPagamentoService::class)->enviar($payment);

        $this->assertSame(PixPayment::STATUS_FINALIZED, $resultado->status);
        $this->assertSame(self::E2E_ID, $resultado->end_to_end_id);
        $this->assertSame('FINALIZADO', $resultado->bank_state);
        $this->assertNotNull($resultado->finalized_at);
    }

    public function test_envia_o_valor_no_formato_exigido_pela_api(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        app(SicoobPixPagamentoService::class)->enviar($this->pixPayment(1234.50));

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/pagamentos/confirmacao')) {
                return false;
            }

            // Vírgula decimal, sem separador de milhar, e o meio de iniciação
            // que corresponde ao passo de chave DICT.
            return $request['valor'] === '1234,50'
                && $request['meioIniciacao'] === 'CHAVE'
                && $request['endToEndId'] === self::E2E_ID;
        });
    }

    public function test_envia_client_id_e_authorization_em_toda_chamada(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        app(SicoobPixPagamentoService::class)->enviar($this->pixPayment(100.00));

        Http::assertSent(fn($request) => !str_starts_with($request->url(), self::TOKEN_URL)
            ? $request->hasHeader('client_id', 'client-de-teste')
              && $request->hasHeader('Authorization', 'Bearer tok-123')
            : true);
    }

    public function test_omite_o_client_id_no_pix_quando_configurado_para_isso(): void
    {
        // A spec de Pix Pagamentos não declara o header `client_id` (só a de
        // Conta Corrente declara). O flag existe para o caso de o gateway
        // recusar por causa dele — e precisa afetar SÓ as chamadas de Pix.
        config(['sicoob.pix.enviar_client_id' => false]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        app(SicoobPixPagamentoService::class)->enviar($this->pixPayment(100.00));

        // Nenhuma chamada de Pix leva o header...
        Http::assertSent(fn($request) => !str_contains($request->url(), '/pagamentos')
            || !$request->hasHeader('client_id'));

        // ...e a de saldo continua levando, porque a spec dela exige.
        Http::assertSent(fn($request) => !str_contains($request->url(), '/saldo')
            || $request->hasHeader('client_id', 'client-de-teste'));

        // O Authorization não é afetado pelo flag em nenhuma das duas.
        Http::assertSent(fn($request) => str_starts_with($request->url(), self::TOKEN_URL)
            || $request->hasHeader('Authorization', 'Bearer tok-123'));
    }

    /* =====================================================================
     | Token
     |=====================================================================*/

    public function test_reaproveita_o_token_em_cache_entre_pagamentos(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::sequence()
                ->push($this->iniciacaoOk())
                ->push($this->iniciacaoOk(e2e: self::E2E_ID_2)),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $service = app(SicoobPixPagamentoService::class);
        $service->enviar($this->pixPayment(100.00));
        $service->enviar($this->pixPayment(150.00));

        // Dois tokens, não quatro: um por CONJUNTO DE ESCOPOS (Pix Pagamentos e
        // Conta Corrente pedem escopos diferentes), reaproveitados no segundo
        // pagamento. O cache existe para não pedir credencial nova a cada
        // transferência — num lote de 40 contratos isso seriam 80 idas ao SSO.
        $this->assertSame(
            2,
            $this->requisicoesDeToken(),
            'O token deveria ter sido pedido uma vez por conjunto de escopos, e reaproveitado depois.'
        );
    }

    public function test_pede_token_novo_quando_o_cache_expira(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::sequence()
                ->push(['access_token' => 'tok-antigo', 'expires_in' => 300])
                ->push(['access_token' => 'tok-antigo', 'expires_in' => 300])
                ->push(['access_token' => 'tok-novo', 'expires_in' => 300])
                ->push(['access_token' => 'tok-novo', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::sequence()
                ->push($this->iniciacaoOk())
                ->push($this->iniciacaoOk(e2e: self::E2E_ID_2)),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $service = app(SicoobPixPagamentoService::class);
        $service->enviar($this->pixPayment(100.00));

        // Simula a expiração: é o que acontece 240s depois do primeiro token.
        Cache::flush();

        $service->enviar($this->pixPayment(150.00));

        $this->assertSame(4, $this->requisicoesDeToken(), 'Com o cache vazio, o token tem de ser pedido de novo.');

        // O segundo pagamento não pode ter seguido com a credencial vencida.
        Http::assertSent(fn($request) => str_ends_with($request->url(), '/pagamentos/confirmacao')
            && $request->hasHeader('Authorization', 'Bearer tok-novo'));
    }

    private function requisicoesDeToken(): int
    {
        return collect(Http::recorded())
            ->filter(fn($par) => str_starts_with($par[0]->url(), self::TOKEN_URL))
            ->count();
    }

    public function test_falha_de_autenticacao_nao_envia_pagamento(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 401),
        ]);

        $payment = $this->pixPayment(100.00);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
            $this->fail('Deveria ter lançado SicoobAuthenticationException.');
        } catch (SicoobAuthenticationException $e) {
            $this->assertStringContainsString('recusou as credenciais', $e->getMessage());
        }

        // O que importa aqui não é a exceção: é que nenhuma requisição de
        // pagamento chegou a sair.
        Http::assertNotSent(fn($request) => str_contains($request->url(), '/pagamentos'));
        $this->assertSame(PixPayment::STATUS_PENDING, $payment->fresh()->status);
    }

    /* =====================================================================
     | Recusas — nada saiu da conta
     |=====================================================================*/

    public function test_estado_rejeitado_marca_o_pagamento_como_recusado(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response(
                $this->retornoPagamento('REJEITADO') + ['detalheRejeicao' => 'Conta destino encerrada']
            ),
        ]);

        $payment = $this->pixPayment(100.00);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
            $this->fail('Deveria ter lançado SicoobPaymentRejectedException.');
        } catch (SicoobPaymentRejectedException $e) {
            $this->assertSame('Conta destino encerrada', $e->detalheRejeicao);
        }

        $payment->refresh();

        $this->assertSame(PixPayment::STATUS_REJECTED, $payment->status);
        $this->assertSame('Conta destino encerrada', $payment->rejection_detail);
        // Recusa é resposta definitiva e negativa: pode-se tentar de novo.
        $this->assertTrue($payment->canBeRetried());
    }

    public function test_estado_nao_realizado_acentuado_e_lido_como_recusa(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            // O Sicoob devolve com til. Ler isso errado marcaria como
            // "desconhecido" um pagamento que o banco recusou com todas as letras.
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('NÃO_REALIZADO')),
        ]);

        $payment = $this->pixPayment(100.00);

        $this->expectException(SicoobPaymentRejectedException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            $this->assertSame(PixPayment::STATUS_REJECTED, $payment->fresh()->status);
        }
    }

    public function test_erro_400_com_violacoes_vira_recusa_com_detalhe(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response([
                'type' => 'https://pix.bcb.gov.br/api/v2/error/RequisicaoInvalida',
                'title' => 'Requisição inválida.',
                'status' => 400,
                'detail' => 'A requisição possui dados inválidos.',
                'violacoes' => [
                    ['razao' => "Dados do campo 'origem' precisam ser preenchidos manualmente.", 'propriedade' => 'origem'],
                ],
            ], 400),
        ]);

        $payment = $this->pixPayment(100.00);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
            $this->fail('Deveria ter lançado SicoobPaymentRejectedException.');
        } catch (SicoobPaymentRejectedException $e) {
            $this->assertStringContainsString('origem', (string) $e->detalheRejeicao);
        }

        $payment->refresh();

        // 4xx é recusa analisada: o banco leu a requisição e negou. Não debitou.
        $this->assertSame(PixPayment::STATUS_REJECTED, $payment->status);
        $this->assertTrue($payment->canBeRetried());
    }

    public function test_saldo_insuficiente_aborta_antes_de_confirmar(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 40.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $payment = $this->pixPayment(250.00);

        $this->expectException(SicoobInsufficientFundsException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            // A confirmação nunca pode ter saído: é o ponto inteiro da pré-checagem.
            Http::assertNotSent(fn($request) => str_ends_with($request->url(), '/pagamentos/confirmacao'));
            $this->assertSame(PixPayment::STATUS_PENDING, $payment->fresh()->status);
        }
    }

    public function test_consulta_de_saldo_indisponivel_nao_bloqueia_o_pagamento(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            // A API de saldo é uma cortesia; quem decide por saldo é o banco,
            // na confirmação. Fora do ar, ela não pode travar o financeiro.
            self::CC_URL . '/saldo*' => Http::response(['mensagem' => 'Serviço indisponível'], 503),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $resultado = app(SicoobPixPagamentoService::class)->enviar($this->pixPayment(250.00));

        $this->assertSame(PixPayment::STATUS_FINALIZED, $resultado->status);
    }

    public function test_chave_de_outro_titular_aborta_antes_de_confirmar(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            // O DICT devolve um CPF que não é o do freelancer cadastrado.
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk(documento: '99988877766', nome: 'OUTRA PESSOA')),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $payment = $this->pixPayment(250.00);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
            $this->fail('Deveria ter lançado SicoobPayeeMismatchException.');
        } catch (SicoobPayeeMismatchException $e) {
            $this->assertSame('OUTRA PESSOA', $e->actualName);
        }

        // A iniciação não move dinheiro, então parar aqui não deixa nada pela
        // metade — e o dinheiro não foi para o titular errado.
        Http::assertNotSent(fn($request) => str_ends_with($request->url(), '/pagamentos/confirmacao'));
        $this->assertSame(PixPayment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_valor_acima_do_teto_nao_chega_a_falar_com_o_banco(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 999999.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        config(['sicoob.pix.max_amount' => 1000]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($this->pixPayment(4500.00));
        } finally {
            Http::assertNothingSent();
        }
    }

    /* =====================================================================
     | Desfecho desconhecido — o caso que proíbe retry
     |=====================================================================*/

    public function test_timeout_na_confirmacao_marca_desfecho_desconhecido(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            // A requisição subiu e a resposta não voltou: o Pix PODE ter sido
            // processado do outro lado.
            self::PIX_URL . '/pagamentos/confirmacao' => fn() => throw new ConnectionException('cURL error 28: Operation timed out'),
        ]);

        $payment = $this->pixPayment(250.00);

        $this->expectException(SicoobPaymentOutcomeUnknownException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            $payment->refresh();

            $this->assertSame(PixPayment::STATUS_UNKNOWN, $payment->status);
            // O endToEndId ficou guardado: é por ele que a reconciliação vai
            // descobrir o que aconteceu, sem reenviar nada.
            $this->assertSame(self::E2E_ID, $payment->end_to_end_id);
            $this->assertFalse($payment->canBeRetried(), 'Desfecho desconhecido NUNCA pode liberar reenvio.');
            $this->assertTrue($payment->needsManualCheck());
        }
    }

    public function test_erro_500_na_confirmacao_tambem_e_desfecho_desconhecido(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response(['title' => 'Erro interno'], 500),
        ]);

        $payment = $this->pixPayment(250.00);

        $this->expectException(SicoobPaymentOutcomeUnknownException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            // Conservador de propósito: um 5xx quase sempre significa "não
            // processei", e "quase sempre" não basta quando o erro é pagar duas vezes.
            $this->assertSame(PixPayment::STATUS_UNKNOWN, $payment->fresh()->status);
        }
    }

    public function test_pagamento_desconhecido_nao_pode_ser_reenviado(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos' => Http::response($this->iniciacaoOk()),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_UNKNOWN,
            'end_to_end_id' => self::E2E_ID,
        ])->save();

        // Mesmo chamado à mão, o serviço recusa: a guarda não depende de o
        // chamador ser bem-comportado.
        $this->expectException(\App\Exceptions\Sicoob\SicoobException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            Http::assertNothingSent();
        }
    }

    /* =====================================================================
     | Reconciliação
     |=====================================================================*/

    public function test_reconciliacao_resolve_pagamento_desconhecido_que_tinha_dado_certo(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::PIX_URL . '/pagamentos/' . self::E2E_ID => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_UNKNOWN,
            'end_to_end_id' => self::E2E_ID,
            'confirmed_at' => now()->subMinutes(5),
        ])->save();

        $resultado = app(SicoobPixPagamentoService::class)->reconciliar($payment);

        // O Pix tinha sido processado; só a resposta é que se perdeu.
        $this->assertSame(PixPayment::STATUS_FINALIZED, $resultado->status);
        $this->assertNotNull($resultado->finalized_at);
    }

    public function test_reconciliacao_descobre_que_o_pagamento_nao_saiu(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::PIX_URL . '/pagamentos/' . self::E2E_ID => Http::response(
                $this->retornoPagamento('REJEITADO') + ['detalheRejeicao' => 'Saldo insuficiente']
            ),
        ]);

        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_UNKNOWN,
            'end_to_end_id' => self::E2E_ID,
        ])->save();

        try {
            app(SicoobPixPagamentoService::class)->reconciliar($payment);
        } catch (SicoobPaymentRejectedException $e) {
            // Esperado: a recusa é propagada para quem chamou.
        }

        $payment->refresh();

        $this->assertSame(PixPayment::STATUS_REJECTED, $payment->status);
        // Agora sim: o banco confirmou que nada saiu, então reenviar é seguro.
        $this->assertTrue($payment->canBeRetried());
    }

    public function test_404_em_pagamento_nunca_confirmado_libera_nova_tentativa(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::PIX_URL . '/pagamentos/' . self::E2E_ID => Http::response([
                'type' => 'https://pix.bcb.gov.br/api/v2/error/NaoEncontrado',
                'title' => 'Not found',
                'status' => 404,
            ], 404),
        ]);

        // Worker morto entre a iniciação e a confirmação: o id foi reservado,
        // mas nenhum pagamento chegou a existir.
        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_INITIATED,
            'end_to_end_id' => self::E2E_ID,
            'confirmed_at' => null,
        ])->save();

        app(SicoobPixPagamentoService::class)->reconciliar($payment);

        $payment->refresh();

        $this->assertSame(PixPayment::STATUS_FAILED, $payment->status);
        $this->assertTrue($payment->canBeRetried(), 'Sem confirmação enviada, nada saiu — o contrato tem de voltar a aceitar pagamento.');
    }

    public function test_404_em_pagamento_ja_confirmado_nao_libera_nova_tentativa(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::PIX_URL . '/pagamentos/' . self::E2E_ID => Http::response(['title' => 'Not found'], 404),
        ]);

        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_UNKNOWN,
            'end_to_end_id' => self::E2E_ID,
            'confirmed_at' => now()->subMinutes(2),
        ])->save();

        // 404 depois de confirmar não é prova de que não pagou — pode ser
        // latência de liquidação. O caso continua aberto.
        $this->expectException(\App\Exceptions\Sicoob\SicoobPaymentNotFoundException::class);

        try {
            app(SicoobPixPagamentoService::class)->reconciliar($payment);
        } finally {
            $payment->refresh();

            $this->assertSame(PixPayment::STATUS_UNKNOWN, $payment->status);
            $this->assertFalse($payment->canBeRetried());
        }
    }

    public function test_titular_e_conferido_de_novo_ao_reentrar_em_pagamento_ja_iniciado(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            self::CC_URL . '/saldo*' => Http::response(['saldo' => 10000.00]),
            self::PIX_URL . '/pagamentos/confirmacao' => Http::response($this->retornoPagamento('FINALIZADO')),
        ]);

        // Pagamento que já passou pela iniciação, mas com titular divergente
        // gravado. Reentrar não pode pular a conferência.
        $payment = $this->pixPayment(250.00);
        $payment->forceFill([
            'status' => PixPayment::STATUS_INITIATED,
            'end_to_end_id' => self::E2E_ID,
            'payee_document' => '99988877766',
            'payee_name' => 'OUTRA PESSOA',
        ])->save();

        $this->expectException(SicoobPayeeMismatchException::class);

        try {
            app(SicoobPixPagamentoService::class)->enviar($payment);
        } finally {
            Http::assertNotSent(fn($request) => str_ends_with($request->url(), '/pagamentos/confirmacao'));
            $this->assertSame(PixPayment::STATUS_FAILED, $payment->fresh()->status);
        }
    }

    /* =====================================================================
     | Apoio
     |=====================================================================*/

    private function pixPayment(float $valor): PixPayment
    {
        $freelancer = Freelancer::create([
            'name' => 'Fulano de Tal',
            'cpf' => '28133847044',
            'pix_key' => '28133847044',
        ]);

        $funcao = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 20]);

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'price' => $valor,
            'total_hours' => 4,
        ]);

        return PixPayment::create([
            'freelancer_service_id' => $service->id,
            'freelancer_id' => $freelancer->id,
            'idempotency_key' => (string) Str::uuid(),
            'pix_key' => $freelancer->pix_key,
            'amount' => $valor,
            'description' => 'Serviço freelancer #' . $service->id,
            'status' => PixPayment::STATUS_PENDING,
            'environment' => 'sandbox',
            'requested_by' => 1,
        ]);
    }

    /** Resposta do passo 1 (`POST /pagamentos`), no formato da especificação. */
    private function iniciacaoOk(string $documento = '28133847044', string $nome = 'FULANO DE TAL', ?string $e2e = null): array
    {
        return [
            'endToEndId' => $e2e ?? self::E2E_ID,
            'chave' => '28133847044',
            'tipo' => 'CPF',
            'proprietario' => [
                'identificador' => $documento,
                'nome' => $nome,
                'tipo' => 'FISICO',
            ],
        ];
    }

    /** `RetornoPagamento`, no formato da especificação. */
    private function retornoPagamento(string $estado): array
    {
        return [
            'endToEndId' => self::E2E_ID,
            'estado' => $estado,
            'valor' => 250.00,
            'horario' => '2026-08-04T12:00:00Z',
            'origem' => ['ispb' => '75657692', 'nome' => 'CLUBE'],
            'destino' => ['ispb' => '00000000', 'nome' => 'FULANO DE TAL'],
        ];
    }

    /**
     * O serviço confere se o certificado existe e é legível antes de montar a
     * requisição. Na suíte não há certificado de verdade — e nem deveria haver.
     */
    private function fakeCertificate(): string
    {
        $path = storage_path('framework/testing/sicoob-cert-fake.pem');

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        if (!file_exists($path)) {
            file_put_contents($path, "-- certificado de teste, sem valor criptográfico --\n");
        }

        return $path;
    }
}
