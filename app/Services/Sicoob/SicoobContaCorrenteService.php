<?php

namespace App\Services\Sicoob;

use App\Exceptions\Sicoob\SicoobException;
use App\Exceptions\Sicoob\SicoobInsufficientFundsException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * API Conta Corrente do Sicoob — usada aqui só para uma coisa: olhar o saldo
 * antes de confirmar um Pix.
 *
 * A checagem existe para transformar uma rejeição do banco em um erro claro
 * ANTES de qualquer movimentação. Mas ela é uma cortesia, não a autoridade:
 * entre a consulta e a confirmação outra saída pode ter zerado a conta, e quem
 * de fato recusa por saldo é o Sicoob. Por isso uma FALHA na consulta não
 * bloqueia o pagamento — só a resposta explícita de "não tem saldo" bloqueia.
 *
 * Endpoint conferido na especificação oficial "Conta Corrente 2.1.1.43":
 * GET /saldo?numeroContaCorrente=... → { saldo, saldoLimite, saldoBloqueado }
 */
class SicoobContaCorrenteService
{
    public function __construct(private SicoobAuthService $auth)
    {
    }

    /**
     * Aborta quando o saldo disponível não cobre o valor.
     *
     * @throws SicoobInsufficientFundsException
     */
    public function assertSaldoSuficiente(float $valor): void
    {
        if (!config('sicoob.conta_corrente.enabled', true)) {
            return;
        }

        $saldo = $this->saldoDisponivel();

        // Consulta indisponível: seguimos em frente e deixamos o banco decidir.
        // Bloquear aqui pararia pagamentos legítimos por causa de uma API de
        // consulta fora do ar, que não é o assunto.
        if ($saldo === null) {
            return;
        }

        if ($saldo < $valor) {
            throw new SicoobInsufficientFundsException(
                'Saldo insuficiente para o Pix: disponível R$ ' . number_format($saldo, 2, ',', '.')
                . ', necessário R$ ' . number_format($valor, 2, ',', '.') . '.',
                ['saldo_disponivel' => $saldo, 'valor' => $valor]
            );
        }
    }

    /**
     * Saldo que pode ser gasto agora, ou null quando a consulta não pôde ser
     * feita. O limite de crédito (cheque especial) NÃO entra na conta: pagar
     * freelancer entrando no rotativo é uma decisão de gestão, não um efeito
     * colateral de um clique em "Dar baixa".
     */
    public function saldoDisponivel(): ?float
    {
        try {
            $response = $this->auth
                ->client(config('sicoob.scopes.conta_corrente'))
                ->get(rtrim((string) config('sicoob.conta_corrente.base_url'), '/') . '/saldo', array_filter([
                    'numeroContaCorrente' => config('sicoob.conta_corrente.numero_conta'),
                ]));

            if ($response->failed()) {
                Log::channel('sicoob')->warning('Sicoob: consulta de saldo falhou — pagamento segue sem a pré-checagem', [
                    'status' => $response->status(),
                    'mensagem' => $response->json('mensagem'),
                    'codigo' => $response->json('codigo'),
                ]);

                return null;
            }

            // A especificação descreve o objeto na raiz, mas a API entrega
            // envelopado em `resultado` em algumas versões. Aceitamos os dois
            // em vez de apostar em um.
            $saldo = $response->json('resultado.saldo') ?? $response->json('saldo');

            if (!is_numeric($saldo)) {
                Log::channel('sicoob')->warning('Sicoob: resposta de saldo sem o campo esperado', [
                    'chaves' => array_keys((array) $response->json()),
                ]);

                return null;
            }

            return (float) $saldo;
        } catch (SicoobException $e) {
            // Certificado/autenticação quebrados aparecem aqui primeiro, mas
            // quem deve interromper o fluxo por isso é o envio do Pix, não a
            // consulta de saldo. Registramos e seguimos.
            Log::channel('sicoob')->warning('Sicoob: não foi possível consultar o saldo', [
                'erro' => $e->getMessage(),
                'contexto' => $e->context(),
            ]);

            return null;
        } catch (Throwable $e) {
            Log::channel('sicoob')->warning('Sicoob: erro inesperado na consulta de saldo', [
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
