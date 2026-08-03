<?php

namespace App\Services\Lara;

/**
 * Resultado de uma pergunta feita à Lara.
 *
 * `texto` nunca vem vazio: quando a chamada falha, ele carrega a frase de
 * fallback e é o `status` que conta o que realmente aconteceu. Essa separação
 * existe porque a própria IA usa a mesma frase ("Vou te transferir para o
 * setor responsável...") quando não sabe responder — sem o status, uma queda
 * de rede e uma transferência legítima ficariam idênticas no histórico, e não
 * haveria como medir a taxa de erro real.
 */
class LaraAnswer
{
    /** A IA respondeu. Pode ser uma resposta útil ou o fallback dela própria. */
    public const STATUS_OK = 'ok';

    /** Não chegamos a ter resposta: rede, HTTP != 2xx ou corpo inesperado. */
    public const STATUS_ERRO = 'erro';

    /** A integração está desligada por configuração — nem tentamos chamar. */
    public const STATUS_DESATIVADO = 'desativado';

    public function __construct(
        public readonly string $texto,
        public readonly string $status,
        public readonly int $latenciaMs = 0,
        public readonly ?string $erro = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === self::STATUS_OK;
    }
}
