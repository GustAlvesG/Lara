<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de uma operação de reserva social.
 *
 * Carrega um `reason` estável além da mensagem: o app de locação decide o que
 * mostrar pelo código, nunca lendo o texto em português — que muda quando
 * alguém melhora a redação. Mesmo padrão de SelfServiceLightingException.
 */
class SocialBookingException extends RuntimeException
{
    /** O espaço não existe, está inativo ou não é de um grupo social. */
    public const PLACE_NOT_SOCIAL = 'place_not_social';

    /** Data ocupada: outra reserva no espaço ou num espaço que conflita com ele. */
    public const DATE_UNAVAILABLE = 'date_unavailable';

    /** Regra do clube bloqueia o dia (manutenção, evento interno). */
    public const DATE_BLOCKED = 'date_blocked';

    /** Fora da antecedência mínima/máxima, dia da semana fechado ou data passada. */
    public const OUT_OF_WINDOW = 'out_of_window';

    /** O sócio atingiu um dos limites do grupo. */
    public const LIMIT_REACHED = 'limit_reached';

    /** A lista de convidados passa do limite de não sócios do espaço. */
    public const OVER_CAPACITY = 'over_capacity';

    /** Bebida inexistente ou fora do catálogo ativo. */
    public const BEVERAGE_UNAVAILABLE = 'beverage_unavailable';

    /** Quantidade de um item do catálogo acima do máximo por reserva. */
    public const ITEM_LIMIT = 'item_limit';

    /** Horário de início ausente, mal formado ou fora da faixa do espaço. */
    public const INVALID_TIME = 'invalid_time';

    /** Convidado sem nome, com CPF inválido ou repetido. */
    public const INVALID_GUEST = 'invalid_guest';

    /** A ação não cabe no estágio atual da reserva. */
    public const INVALID_STAGE = 'invalid_stage';

    /** Contrato ok: a pré-reserva não aceita mais trocas. */
    public const CONTRACT_CLOSED = 'contract_closed';

    /** Aprovar exige o contrato marcado como ok. */
    public const CONTRACT_PENDING = 'contract_pending';

    /** Pagamento recusado: fora do prazo ou valor diferente do total. */
    public const PAYMENT_MISMATCH = 'payment_mismatch';

    /** A reserva não é do sócio da sessão. */
    public const NOT_OWNER = 'not_owner';

    /** Prazo da lista de convidados pelo app já passou. */
    public const GUESTS_LOCKED = 'guests_locked';

    /** Desconto negativo, maior que o valor ou sem motivo. */
    public const INVALID_DISCOUNT = 'invalid_discount';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** Corpo de resposta da API: mensagem para mostrar e código para decidir. */
    public function toArray(): array
    {
        return array_filter([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'context' => $this->context ?: null,
        ], fn ($value) => $value !== null);
    }
}
