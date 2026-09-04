<?php

namespace App\Services\Poli;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envio de mensagens pela Poli Digital.
 *
 * Contrapartida do PoliMessageParser, que só lê o que chega. Aqui só existe
 * texto simples: a conversa já está ativa (o associado acabou de percorrer o
 * fluxo do "Carro de Aplicativo" no WhatsApp), então não há template a
 * aprovar nem janela a abrir.
 *
 * Duas decisões:
 *
 * 1. Nada lança exceção. Quem chama está concluindo um acesso na portaria —
 *    a Poli fora do ar não pode virar erro de tela nem impedir o registro do
 *    acesso. Falha vira SendMessageResult::failure() e um log.
 *
 * 2. Nada de retry aqui dentro. Repetir é assunto do Job, que é quem conhece
 *    o rate limit da conta e sabe reagendar sem segurar uma requisição web.
 */
class PoliMessageService
{
    /**
     * Telefone em E.164 SEM o "+": DDI + DDD + número, só dígitos.
     *
     * O piso de 12 é proposital. Um número brasileiro sem DDI tem 10 ou 11
     * dígitos, e completar o 55 por conta própria seria adivinhar — no pior
     * caso, mandando a mensagem para um estranho. Recusar é o erro barato:
     * todo telefone que entra por aqui vem da Poli, que já entrega com DDI.
     */
    private const PHONE_MIN_DIGITS = 12;
    private const PHONE_MAX_DIGITS = 15;

    /**
     * O que a Poli devolve num envio aceito, medido num envio REAL:
     * HTTP 200 com o CORPO VAZIO. Não há identificador de mensagem para
     * guardar — quem quiser rastrear o que aconteceu com ela depende do
     * webhook de entrada, não da resposta do POST.
     *
     * Os caminhos abaixo continuam aqui porque a ausência de corpo é uma
     * observação de UM endpoint num dia: se a Poli passar a devolver algo,
     * o uuid é achado sem alterar código. Corpo não-vazio e irreconhecível
     * vira log, não erro — o envio já aconteceu.
     */
    private const UUID_PATHS = ['data.uuid', 'uuid', 'data.message.uuid', 'message.uuid', 'data.id', 'id'];
    private const STATUS_PATHS = ['data.status', 'status', 'data.message.status'];

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
     * @param string|null $channelUuid Canal de saída; null usa o padrão do .env.
     * @param string|null $contactUuid Identificador do contato na Poli, quando
     *                                 conhecido. É o campo em que a API confia;
     *                                 ver a nota no config sobre o par
     *                                 contact_uuid / contact_channel_uid.
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

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === null) {
            return $this->refuse('telefone fora do formato E.164 sem "+"', $phone);
        }

        $text = trim($text);
        if ($text === '') {
            return $this->refuse('texto vazio', $phone);
        }

        $channelUuid ??= (string) config('poli.default_channel_uuid');
        if ($channelUuid === '') {
            return $this->refuse('canal de envio não configurado', $phone);
        }

        $url = $this->endpoint();
        $body = $this->buildBody($normalizedPhone, $text, $channelUuid, $contactUuid);

        Log::info('Poli: enviando mensagem de texto', [
            'url' => $url,
            'token' => $this->maskToken((string) config('poli.token')),
            'phone' => $this->maskPhone($normalizedPhone),
            'contact_uuid' => $contactUuid,
            'account_channel_uuid' => $channelUuid,
            'chars' => mb_strlen($text),
        ]);

        try {
            $response = Http::withToken((string) config('poli.token'))
                ->acceptJson()
                ->connectTimeout((int) config('poli.http.connect_timeout', 10))
                ->timeout((int) config('poli.http.timeout', 20))
                ->post($url, $body);
        } catch (ConnectionException $e) {
            return $this->transportFailure('conexão: ' . $e->getMessage(), $normalizedPhone);
        } catch (Throwable $e) {
            // O Laravel só converte ALGUNS erros de transporte em
            // ConnectionException — os que o Guzzle classifica como
            // ConnectException (DNS, recusa, timeout). Os demais sobem crus:
            // um certificado que não valida, por exemplo, chega aqui como
            // GuzzleHttp\Exception\RequestException e passaria direto por um
            // catch estreito, quebrando a promessa de que este método não
            // lança. Ver o teste que cobre exatamente esse caso.
            return $this->transportFailure(
                class_basename($e) . ': ' . $e->getMessage(),
                $normalizedPhone
            );
        }

        return $response->successful()
            ? $this->handleSuccess($response, $normalizedPhone)
            : $this->handleFailure($response, $normalizedPhone);
    }

    /* ---------------------------------------------------------------------
     | Montagem da requisição — o único lugar que conhece o formato da Poli
     |---------------------------------------------------------------------*/

    private function endpoint(): string
    {
        $path = str_replace(
            '{account_uuid}',
            (string) config('poli.account_uuid'),
            (string) config('poli.send.path')
        );

        return rtrim((string) config('poli.base_url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(string $phone, string $text, string $channelUuid, ?string $contactUuid): array
    {
        return [
            'provider' => config('poli.send.provider'),
            'account_channel_uuid' => $channelUuid,
            'type' => config('poli.send.text_type'),
            'version' => config('poli.send.version'),
            'direction' => 'OUT',
            'contact' => array_filter([
                'type' => config('poli.send.contact_type'),
                'contact_uuid' => $contactUuid,
                'contact_channel_uid' => config('poli.send.include_contact_channel_uid')
                    ? $phone . config('poli.send.contact_channel_uid_suffix')
                    : null,
            ], fn ($value) => filled($value)),
            'author' => $this->author(),
            // Sem `attachments`: é texto puro. O componente `body.text` é o
            // mesmo que a Poli nos devolve na entrada.
            'components' => [
                'body' => ['text' => $text],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function author(): array
    {
        $userUuid = config('poli.send.author.user_uuid');

        if (filled($userUuid)) {
            return [
                'type' => 'USER',
                'user_uuid' => $userUuid,
                'name' => config('poli.send.author.name'),
            ];
        }

        return [
            'type' => 'APPLICATION',
            'name' => config('poli.send.author.name'),
        ];
    }

    /* ---------------------------------------------------------------------
     | Leitura da resposta
     |---------------------------------------------------------------------*/

    private function handleSuccess(Response $response, string $phone): SendMessageResult
    {
        $uuid = $this->firstPath($response, self::UUID_PATHS);
        $status = $this->firstPath($response, self::STATUS_PATHS);

        // Corpo vazio é o normal (ver UUID_PATHS). Só um corpo COM conteúdo
        // que não reconhecemos merece registro: aí a Poli mudou algo, e é o
        // payload no log que permite ajustar os caminhos.
        if ($uuid === null && trim($response->body()) !== '') {
            Log::info('Poli: envio aceito em formato de resposta não mapeado', [
                'phone' => $this->maskPhone($phone),
                'http_status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        Log::info('Poli: mensagem enviada', [
            'phone' => $this->maskPhone($phone),
            'message_uuid' => $uuid,
            'status' => $status,
            'http_status' => $response->status(),
        ]);

        return SendMessageResult::ok($uuid, $status, $response->status());
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

    /**
     * @param  string[]  $paths
     */
    private function firstPath(Response $response, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $response->json($path);

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_int($value)) {
                return (string) $value;
            }
        }

        return null;
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
