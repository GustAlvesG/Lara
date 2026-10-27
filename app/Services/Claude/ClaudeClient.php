<?php

namespace App\Services\Claude;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Exceptions\ClaudeException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Porta única do Lara para a API do Claude (Anthropic), pelo SDK oficial
 * `anthropic-ai/sdk`. Quem usa pede uma resposta em JSON num formato
 * (structured outputs) e recebe o array já validado pelo esquema — ou uma
 * ClaudeException com a mensagem para a tela.
 *
 * A chave vem de `config('claude.api_key')` (ANTHROPIC_API_KEY); sem chave,
 * `isConfigured()` é falso e as telas escondem o recurso.
 */
class ClaudeClient
{
    private ?Client $client = null;

    /** @param  ClientInterface|null  $transporter  cliente HTTP (PSR-18) — os testes passam um falso */
    public function __construct(private ?ClientInterface $transporter = null)
    {
    }

    public function isConfigured(): bool
    {
        return filled(config('claude.api_key'));
    }

    /**
     * Uma pergunta, uma resposta em JSON no formato de `$schema`.
     *
     * @param  array<string, mixed>  $schema  JSON Schema (objeto, additionalProperties false)
     * @return array{data: array<string, mixed>, model: string, input_tokens: int, output_tokens: int}
     *
     * @throws ClaudeException
     */
    public function structured(
        string $model,
        string $system,
        string $prompt,
        array $schema,
        int $maxTokens,
        ?string $effort = null,
    ): array {
        if (!$this->isConfigured()) {
            throw new ClaudeException('A integração com o Claude não está configurada (falta a chave da API).');
        }

        $outputConfig = ['format' => ['type' => 'json_schema', 'schema' => $schema]];

        if (filled($effort)) {
            $outputConfig['effort'] = $effort;
        }

        try {
            $message = $this->client()->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: $outputConfig,
            );
        } catch (APIException $e) {
            throw $this->translate($e);
        }

        if ($message->stopReason === 'refusal') {
            Log::warning('Claude recusou o pedido.', [
                'model' => $model,
                'category' => $message->stopDetails?->category,
            ]);

            throw new ClaudeException('O Claude não quis analisar este texto. Tente de novo mais tarde ou faça a conferência manual.');
        }

        if ($message->stopReason === 'max_tokens') {
            throw new ClaudeException('A resposta do Claude ficou longa demais e veio cortada. Tente de novo.');
        }

        $texto = '';

        foreach ($message->content as $bloco) {
            if ($bloco->type === 'text') {
                $texto .= $bloco->text;
            }
        }

        $dados = json_decode($texto, true);

        if (!is_array($dados)) {
            Log::warning('Claude respondeu fora do formato.', ['model' => $model, 'stop_reason' => $message->stopReason]);

            throw new ClaudeException('O Claude respondeu fora do formato esperado. Tente de novo.');
        }

        return [
            'data' => $dados,
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ];
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: (string) config('claude.api_key'),
            requestOptions: array_filter([
                'timeout' => (float) config('claude.timeout', 90),
                'maxRetries' => (int) config('claude.max_retries', 1),
                'transporter' => $this->transporter,
            ], fn($v) => $v !== null),
        );
    }

    /** Erro do SDK → mensagem para a tela; o detalhe vai para o log. */
    private function translate(APIException $e): ClaudeException
    {
        Log::warning('Falha na API do Claude.', [
            'exception' => $e::class,
            'status' => $e instanceof APIStatusException ? $e->status : null,
            'type' => $e instanceof APIStatusException ? $e->type?->value : null,
        ]);

        $mensagem = match (true) {
            $e instanceof AuthenticationException,
            $e instanceof PermissionDeniedException => 'A chave da API do Claude foi recusada. Avise a Informática.',
            $e instanceof RateLimitException => 'O limite de uso da API do Claude foi atingido. Tente de novo em alguns minutos.',
            $e instanceof BadRequestException => 'A API do Claude recusou o pedido. Avise a Informática.',
            $e instanceof APIStatusException && $e->status === 402 => 'A conta da API do Claude está sem crédito. Avise a Informática.',
            $e instanceof APIConnectionException => 'Não foi possível falar com o Claude agora (sem conexão ou demorou demais). Tente de novo.',
            default => 'O Claude está indisponível no momento. Tente de novo em alguns minutos.',
        };

        return new ClaudeException($mensagem, 0, $e);
    }
}
