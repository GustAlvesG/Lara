<?php

namespace App\Services\Lara;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cliente HTTP do agente de IA (Lara), que roda numa VM própria.
 *
 * Contrato do serviço:
 *   POST /perguntar  {usuario_id, mensagem} -> {resposta}
 *   POST /reiniciar  {usuario_id}           -> {status: "ok"}
 *
 * Duas decisões que valem explicação:
 *
 * 1. Nenhum método lança exceção. Quem chama está no meio de uma requisição
 *    web com um funcionário esperando na tela — indisponibilidade da IA vira
 *    uma frase de fallback, não uma tela de erro.
 *
 * 2. Não há retry. O serviço avisa que uma resposta legítima pode levar até
 *    30s porque o modelo roda em CPU; repetir a chamada depois de um timeout
 *    dobraria a carga justamente quando a VM já está saturada.
 */
class LaraClient
{
    /**
     * Sem base_url configurada a integração fica desligada sozinha — é o que
     * permite subir o código antes de o endereço da VM estar liberado.
     */
    public function enabled(): bool
    {
        return (bool) config('services.lara.enabled') && filled(config('services.lara.base_url'));
    }

    public function ask(string $usuarioId, string $mensagem): LaraAnswer
    {
        if (!$this->enabled()) {
            return new LaraAnswer($this->fallback(), LaraAnswer::STATUS_DESATIVADO, 0, 'integração desligada');
        }

        $inicio = microtime(true);

        try {
            $response = Http::timeout((int) config('services.lara.timeout', 30))
                ->acceptJson()
                ->post($this->url('/perguntar'), [
                    'usuario_id' => $usuarioId,
                    'mensagem' => $this->truncate($mensagem),
                ]);
        } catch (ConnectionException $e) {
            return $this->falha($usuarioId, $inicio, 'conexão: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return $this->falha($usuarioId, $inicio, 'HTTP ' . $response->status());
        }

        $texto = $response->json('resposta');

        if (!is_string($texto) || trim($texto) === '') {
            return $this->falha($usuarioId, $inicio, 'corpo sem o campo "resposta"');
        }

        return new LaraAnswer(trim($texto), LaraAnswer::STATUS_OK, $this->elapsed($inicio));
    }

    /**
     * Limpa o histórico daquele usuário do lado da IA (botão "Nova conversa").
     *
     * Devolve false em vez de falhar: se a IA não responder, a conversa nova
     * começa mesmo assim do lado do portal. O pior caso é a Lara ainda lembrar
     * do assunto anterior por um tempo, o que é bem menos ruim que travar o
     * botão.
     */
    public function reset(string $usuarioId): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        try {
            return Http::timeout((int) config('services.lara.reset_timeout', 10))
                ->acceptJson()
                ->post($this->url('/reiniciar'), ['usuario_id' => $usuarioId])
                ->successful();
        } catch (ConnectionException $e) {
            Log::warning('Lara: falha ao reiniciar o histórico', [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function falha(string $usuarioId, float $inicio, string $motivo): LaraAnswer
    {
        $latencia = $this->elapsed($inicio);

        // O texto da pergunta não entra no log: ele já está em `lara_messages`,
        // e o log da aplicação é lido por mais gente do que o banco.
        Log::warning('Lara: pergunta sem resposta', [
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
            'latencia_ms' => $latencia,
        ]);

        return new LaraAnswer($this->fallback(), LaraAnswer::STATUS_ERRO, $latencia, $motivo);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.lara.base_url'), '/') . $path;
    }

    private function truncate(string $mensagem): string
    {
        return Str::limit($mensagem, (int) config('services.lara.max_input_chars', 1000), '');
    }

    private function elapsed(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }

    private function fallback(): string
    {
        return (string) config('services.lara.fallback_message');
    }
}
