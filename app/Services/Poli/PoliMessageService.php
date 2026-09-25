<?php

namespace App\Services\Poli;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Envio de texto pela Poli para quem não pode quebrar.
 *
 * Camada sobre o PoliClient. Quem chama está concluindo um acesso na
 * portaria — a Poli fora do ar não pode virar erro de tela nem impedir o
 * registro do acesso. Por isso nada aqui lança: toda falha vira
 * SendMessageResult e um log.
 *
 * O client já repete na hora os soluços de rede (conexão, 5xx, 429). A espera
 * longa continua sendo do Job, que conhece o rate limit da conta e reagenda
 * sem segurar um worker.
 */
class PoliMessageService
{
    /**
     * Telefone em E.164 SEM o "+": DDI + DDD + número, só dígitos.
     *
     * Mais estrito que o PoliClient, de propósito. Um número brasileiro sem
     * DDI tem 10 ou 11 dígitos, e completar o 55 por conta própria seria
     * adivinhar. Aqui todo telefone vem da Poli, que já entrega com DDI — e,
     * na prática, o envio sai pelo contact_uuid, sem usar telefone nenhum.
     */
    private const PHONE_MIN_DIGITS = 12;
    private const PHONE_MAX_DIGITS = 15;

    public function __construct(private readonly PoliClient $client) {}

    /**
     * Sem chave, sem conta ou desligada no .env, a integração fica inerte.
     */
    public function enabled(): bool
    {
        return (bool) config('poli.enabled')
            && filled(config('poli.token'))
            && filled(config('poli.account_uuid'))
            && filled(config('poli.base_url'));
    }

    /**
     * @param string      $phone       E.164 sem "+" (ex.: 5524999998888).
     * @param string      $text        Conteúdo da mensagem.
     * @param string|null $channelUuid Canal de saída; null usa o do .env.
     * @param string|null $contactUuid Identificador do contato na Poli. Quando
     *                                 existe, o envio sai por ele e o telefone
     *                                 nem é consultado.
     */
    public function sendTextByPhone(
        string $phone,
        string $text,
        ?string $channelUuid = null,
        ?string $contactUuid = null
    ): SendMessageResult {
        if (!$this->enabled()) {
            return SendMessageResult::failure('integração Poli desligada');
        }

        $text = trim($text);
        if ($text === '') {
            return $this->refuse('texto vazio', $phone);
        }

        $client = $this->client->usandoCanal($channelUuid);
        if (blank($channelUuid) && blank(config('poli.channel_uuid') ?? config('poli.default_channel_uuid'))) {
            return $this->refuse('canal de envio não configurado', $phone);
        }

        $contactUuid = filled($contactUuid) ? $contactUuid : null;

        if ($contactUuid === null) {
            $normalized = $this->normalizePhone($phone);
            if ($normalized === null) {
                return $this->refuse('telefone fora do formato E.164 sem "+"', $phone);
            }
            $phone = $normalized;
        }

        Log::info('Poli: enviando mensagem de texto', [
            'via' => $contactUuid !== null ? 'contact_uuid' : 'telefone',
            'token' => $this->maskToken((string) config('poli.token')),
            'phone' => $this->maskPhone($phone),
            'contact_uuid' => $contactUuid,
            'chars' => mb_strlen($text),
        ]);

        try {
            $response = $contactUuid !== null
                ? $client->texto($contactUuid, $text)
                : $client->textoPorTelefone($phone, $text);
        } catch (RequestException $e) {
            return $this->handleFailure($e->response, $phone);
        } catch (ConnectionException $e) {
            return $this->transportFailure('conexão: ' . $e->getMessage(), $phone);
        } catch (InvalidArgumentException $e) {
            return $this->refuse($e->getMessage(), $phone);
        } catch (Throwable $e) {
            // O Laravel só converte ALGUNS erros de transporte em
            // ConnectionException — os que o Guzzle classifica como
            // ConnectException (DNS, recusa, timeout). Os demais sobem crus:
            // um certificado que não valida, por exemplo, chega aqui como
            // GuzzleHttp\Exception\RequestException e passaria direto por um
            // catch estreito, quebrando a promessa de que este método não
            // lança. Ver o teste que cobre exatamente esse caso.
            return $this->transportFailure(class_basename($e) . ': ' . $e->getMessage(), $phone);
        }

        return $this->handleSuccess($response, $phone);
    }

    /* ---------------------------------------------------------------------
     | Leitura da resposta
     |---------------------------------------------------------------------*/

    /**
     * Envio aceito de verdade é 201 com o `uuid` da mensagem. 2xx sem uuid
     * NÃO conta como enviado: é o sintoma do endpoint que aceita tudo e não
     * entrega nada (ver config/poli.php). Vira falha definitiva — não
     * retentável, para que um envio que talvez tenha saído não saia duas
     * vezes — e o corpo fica no log.
     */
    private function handleSuccess(array $response, string $phone): SendMessageResult
    {
        $uuid = $response['uuid'] ?? null;

        if (!is_string($uuid) || $uuid === '') {
            Log::warning('Poli: envio respondido sem uuid — não confirmado', [
                'phone' => $this->maskPhone($phone),
                'body' => $response,
            ]);

            return SendMessageResult::failure('Poli respondeu sem uuid da mensagem — envio não confirmado');
        }

        $status = is_string($response['ack'] ?? null) ? $response['ack'] : null;

        Log::info('Poli: mensagem enviada', [
            'phone' => $this->maskPhone($phone),
            'message_uuid' => $uuid,
            'contact_uuid' => $response['contact']['uuid'] ?? null,
            'ack' => $status,
        ]);

        return SendMessageResult::ok($uuid, $status);
    }

    private function handleFailure(Response $response, string $phone): SendMessageResult
    {
        $error = $this->extractError($response);
        $retryAfter = $this->retryAfter($response);

        Log::warning('Poli: mensagem recusada', [
            'phone' => $this->maskPhone($phone),
            'http_status' => $response->status(),
            'retry_after' => $retryAfter,
            'erro' => $error,
            'body' => $response->json() ?? $response->body(),
        ]);

        return SendMessageResult::httpFailure($error, $response->status(), $retryAfter);
    }

    /**
     * Formato de erro confirmado da Poli:
     *   { "message": "...", "errors": { "campo": ["..."] } }
     * A mensagem já vem em português e nomeando o campo — é ela que vai para
     * o log de quem for diagnosticar.
     */
    private function extractError(Response $response): string
    {
        $message = $response->json('message');
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            $flat = Arr::flatten($errors);
            $detail = implode(' ', array_filter($flat, 'is_string'));

            if ($detail !== '') {
                return is_string($message) && $message !== '' && !str_contains($detail, $message)
                    ? $message . ' — ' . $detail
                    : $detail;
            }
        }

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'HTTP ' . $response->status();
    }

    /**
     * A Poli não documenta `Retry-After`. Quando não vier, quem decide a
     * espera é o Job — daí o null em vez de um número inventado aqui.
     */
    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? max(1, (int) $header) : null;
    }

    /* ---------------------------------------------------------------------
     | Telefone e mascaramento
     |---------------------------------------------------------------------*/

    /**
     * Aceita o que o usuário digitou com "+", espaço, parênteses ou hífen e
     * devolve só os dígitos. Devolve null quando o resultado não é um E.164
     * plausível — ver a nota em PHONE_MIN_DIGITS.
     */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < self::PHONE_MIN_DIGITS || strlen($digits) > self::PHONE_MAX_DIGITS) {
            return null;
        }

        // DDI nunca começa com zero; um "0" à frente é sobra de discagem
        // nacional (0XX) e indica que o número não está em E.164.
        if (str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
    }

    /**
     * A requisição não chegou a receber resposta. Vale tentar de novo: a
     * causa costuma ser passageira, e quando não é (certificado, endereço
     * errado) o Job desiste sozinho depois das tentativas.
     */
    private function transportFailure(string $motivo, string $phone): SendMessageResult
    {
        Log::warning('Poli: mensagem não enviada (transporte)', [
            'phone' => $this->maskPhone($phone),
            'erro' => $motivo,
        ]);

        return SendMessageResult::connectionFailure($motivo);
    }

    private function refuse(string $motivo, string $phone): SendMessageResult
    {
        Log::warning('Poli: envio recusado antes da requisição', [
            'motivo' => $motivo,
            'phone' => $this->maskPhone($phone),
        ]);

        return SendMessageResult::failure($motivo);
    }

    /**
     * O log da aplicação é lido por mais gente do que o banco: o telefone do
     * associado sai dele com o miolo coberto.
     */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 6) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 6) . substr($digits, -2);
    }

    private function maskToken(string $token): string
    {
        return $token === '' ? '' : '***' . substr($token, -4);
    }
}
