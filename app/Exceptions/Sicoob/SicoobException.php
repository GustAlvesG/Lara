<?php

namespace App\Exceptions\Sicoob;

use RuntimeException;
use Throwable;

/**
 * Base das falhas da integração Sicoob.
 *
 * Carrega o `contexto`, que é o que vai para o log estruturado. A regra dele é
 * curta e não tem exceção: NUNCA recebe token, senha de certificado ou header
 * Authorization. Se um dado desses chegar aqui, ele vaza para o arquivo de log
 * e para o Ignition em tela.
 */
class SicoobException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Mensagem para o usuário do financeiro. As subclasses sobrescrevem quando
     * têm algo mais útil a dizer do que o texto técnico.
     */
    public function userMessage(): string
    {
        return 'Não foi possível concluir o Pix. A tentativa ficou registrada; procure a TI antes de tentar de novo.';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
