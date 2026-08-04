<?php

namespace App\Services\Lara;

/**
 * Resultado de uma pergunta feita à Lara.
 *
 * `texto` nunca vem vazio: quando algo falha, ele carrega a frase de fallback e
 * é o `status` que conta o que realmente aconteceu. Os quatro status separam as
 * origens possíveis de uma mesma frase na tela — sem isso não dá para medir
 * taxa de erro, porque a IA usa o mesmo texto de transferência tanto quando
 * quebra quanto quando decide, legitimamente, encaminhar o assunto.
 */
class LaraAnswer
{
    /** A IA respondeu de verdade — inclusive quando a resposta dela é encaminhar para um setor. */
    public const STATUS_OK = 'ok';

    /**
     * A IA respondeu, mas sinalizando fallback de sistema dela (`transferir:
     * true`): timeout interno, limite de concorrência ou erro do modelo.
     */
    public const STATUS_FALLBACK = 'fallback';

    /** Não chegamos a ter resposta: rede, HTTP != 2xx ou corpo inesperado. */
    public const STATUS_ERRO = 'erro';

    /** A integração está desligada ou fora do ar — nem tentamos perguntar. */
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
