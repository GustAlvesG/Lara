<?php

namespace App\Services\Poli;

/**
 * Desfecho de um envio para a Poli.
 *
 * Nenhum método do PoliMessageService lança exceção — o chamador está no meio
 * de um acesso sendo liberado na portaria, e a mensagem de aviso não pode
 * derrubar isso. Todo erro vira um destes objetos.
 *
 * `httpStatus` e `retryAfter` existem para UM caso: o 429. É a única falha em
 * que a decisão do Job muda (reagendar em vez de desistir), e ele precisa
 * saber disso sem reinterpretar a string de erro.
 */
class SendMessageResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $messageUuid = null,
        public readonly ?string $status = null,
        public readonly ?string $error = null,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfter = null,
        public readonly bool $retryable = false,
    ) {}

    public static function ok(?string $messageUuid, ?string $status, int $httpStatus): self
    {
        return new self(true, $messageUuid, $status, null, $httpStatus);
    }

    /**
     * Falha definitiva do nosso lado: telefone inválido, texto vazio,
     * integração desligada. Tentar de novo dá exatamente no mesmo.
     */
    public static function failure(string $error): self
    {
        return new self(false, null, null, $error);
    }

    /** A requisição nem chegou lá. Vale tentar de novo. */
    public static function connectionFailure(string $error): self
    {
        return new self(false, null, null, $error, null, null, true);
    }

    /**
     * Falha com resposta do servidor. Só 429 e 5xx merecem nova tentativa:
     * um 422 apontando campo errado no payload vai errar de novo igual.
     */
    public static function httpFailure(string $error, int $httpStatus, ?int $retryAfter = null): self
    {
        return new self(
            false,
            null,
            null,
            $error,
            $httpStatus,
            $retryAfter,
            $httpStatus === 429 || $httpStatus >= 500,
        );
    }

    /**
     * Estourou a cota da aplicação (60/min). Diferente de qualquer outro erro:
     * a mensagem não está perdida, só cedo demais.
     */
    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}
