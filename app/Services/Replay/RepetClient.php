<?php

namespace App\Services\Replay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Cliente da API do servidor do rePET (transmissão ao vivo das quadras).
 * Contrato: docs/funcionalidades/replay.md, seção "Transmissões", e o
 * OpenAPI do próprio servidor (GET {url}/openapi.json).
 *
 * Decisões:
 *
 * 1. Sem retry, nunca. Ponto, set e volume em passo NÃO são idempotentes:
 *    repetir depois de um timeout pode dar dois pontos. Quem decide repetir é
 *    a pessoa, depois de ver o estado (a tela atualiza sozinha).
 *
 * 2. Tudo que dá errado vira RepetUnavailable com mensagem para a tela. O
 *    `detail` do servidor já é português e explica o motivo (409 de stream key
 *    em uso, 422 de quadra sem chave), então ele passa adiante; 401/503 de
 *    token são problema de configuração e viram aviso para a TI.
 *
 * 3. O quadra_id vai na URL: só letras, números, `_` e `-` passam, para que
 *    nada vindo do navegador mude o caminho chamado.
 *
 * Só as rotas com Bearer (`/status`, `/broadcast/*`, `/live/*`). Replay
 * manual e exclusão de clipe ficam fora de propósito.
 */
class RepetClient
{
    public const SIDES = ['a', 'b'];

    public function enabled(): bool
    {
        return filled(config('services.repet.url')) && filled(config('services.repet.token'));
    }

    /** Estado de todas as quadras, sem consultar câmera (rápido; serve para atualizar a tela). */
    public function status(): array
    {
        return $this->send('get', '/status');
    }

    // ── Transmissão com placar ───────────────────────────────────────────

    /**
     * @param  array{nome_a?: ?string, nome_b?: ?string, reset?: bool, volume?: int}  $options
     */
    public function startBroadcast(string $court, array $options = []): array
    {
        $body = array_filter($options, fn ($v) => $v !== null && $v !== '');

        return $this->send('post', "/broadcast/{$this->court($court)}/start", $body, slow: true);
    }

    public function stopBroadcast(string $court): array
    {
        return $this->send('post', "/broadcast/{$this->court($court)}/stop");
    }

    public function point(string $court, string $side, int $delta = 1): array
    {
        return $this->send('post', "/broadcast/{$this->court($court)}/ponto/{$this->side($side)}?delta={$delta}");
    }

    public function set(string $court, string $side, int $delta = 1): array
    {
        return $this->send('post', "/broadcast/{$this->court($court)}/set/{$this->side($side)}?delta={$delta}");
    }

    /** @param  array{pontos_a?: int, pontos_b?: int, sets_a?: int, sets_b?: int}  $score */
    public function putScore(string $court, array $score): array
    {
        return $this->send('put', "/broadcast/{$this->court($court)}/placar", $score);
    }

    public function resetScore(string $court): array
    {
        return $this->send('post', "/broadcast/{$this->court($court)}/placar/zerar");
    }

    /** @param  array{nome_a?: string, nome_b?: string}  $names */
    public function names(string $court, array $names): array
    {
        return $this->send('put', "/broadcast/{$this->court($court)}/nomes", $names);
    }

    public function volume(string $court, int $level): array
    {
        return $this->send('put', "/broadcast/{$this->court($court)}/volume", ['nivel' => $level]);
    }

    // ── Live direta (a câmera empurra ao YouTube, sem placar) ─────────────

    public function startLive(string $court): array
    {
        return $this->send('post', "/live/{$this->court($court)}", slow: true);
    }

    public function stopLive(string $court): array
    {
        return $this->send('post', "/live/{$this->court($court)}/stop", slow: true);
    }

    // ── Canal do YouTube da quadra (stream key e URL RTMP) ────────────────

    /**
     * Destino da quadra. O servidor NUNCA devolve a key aqui, só os 4 últimos
     * caracteres (`stream_key_final`). A rota que devolve a key inteira não é
     * usada pelo Lara, de propósito: a tela não precisa dela.
     */
    public function youtubeConfig(string $court): array
    {
        return $this->send('get', "/quadras/{$this->court($court)}/youtube");
    }

    /**
     * Troca a stream key e/ou a URL RTMP (o que vier; o resto não muda).
     * Quadra no ar → 409; URL fora dos hosts permitidos → 422. A key só vai
     * no corpo: nunca em log, mensagem ou resposta.
     *
     * @param  array{stream_key?: string, rtmp_url?: string}  $changes
     */
    public function updateYoutube(string $court, array $changes): array
    {
        return $this->send('put', "/quadras/{$this->court($court)}/youtube", $changes);
    }

    // ── Infra ─────────────────────────────────────────────────────────────

    public static function validCourt(string $court): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/', $court);
    }

    private function court(string $court): string
    {
        if (! self::validCourt($court)) {
            throw new InvalidArgumentException('quadra_id inválido.');
        }

        return $court;
    }

    private function side(string $side): string
    {
        $side = strtolower($side);

        if (! in_array($side, self::SIDES, true)) {
            throw new InvalidArgumentException('Lado deve ser a ou b.');
        }

        return $side;
    }

    private function http(bool $slow): PendingRequest
    {
        $timeout = $slow
            ? (int) config('services.repet.start_timeout', 30)
            : (int) config('services.repet.timeout', 10);

        return Http::baseUrl(rtrim((string) config('services.repet.url'), '/'))
            ->withToken((string) config('services.repet.token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout);
    }

    private function send(string $method, string $path, array $body = [], bool $slow = false): array
    {
        if (! $this->enabled()) {
            throw new RepetUnavailable('A integração com o servidor do rePET está desligada (REPET_API_URL / REPET_API_TOKEN).');
        }

        $read = $method === 'get';

        try {
            $request = $this->http($slow);
            /** @var Response $response */
            $response = match (true) {
                $read => $request->get($path),
                $body === [] => $request->send(strtoupper($method), $path),
                default => $request->{$method}($path, $body),
            };
        } catch (ConnectionException $e) {
            $refused = str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'Failed to connect');

            throw new RepetUnavailable(
                $refused
                    ? 'O servidor do rePET está fora do ar (ele reinicia todo dia às 06:00). Tente de novo em instantes.'
                    : 'O servidor do rePET não respondeu a tempo. Confira o estado na tela antes de repetir.',
                null,
                ! $read && ! $refused,
            );
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw new RepetUnavailable($this->messageFor($response), $response->status());
    }

    private function messageFor(Response $response): string
    {
        $status = $response->status();

        if ($status === 401 || ($status === 503 && str_contains((string) $response->json('detail'), 'token'))) {
            return 'O servidor do rePET recusou a chave do Lara. Avise a TI (REPET_API_TOKEN).';
        }

        $detail = $response->json('detail');

        // Validação automática do servidor: `detail` vem como lista.
        if (is_array($detail)) {
            $detail = collect($detail)->pluck('msg')->filter()->implode('; ') ?: null;
        }

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        return "O servidor do rePET respondeu com erro (HTTP {$status}).";
    }
}
