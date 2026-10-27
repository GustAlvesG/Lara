<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de uma operação de ingressos (pedido, pagamento, desistência,
 * check-in).
 *
 * Carrega um `reason` estável além da mensagem: o site decide o que mostrar
 * pelo código, nunca lendo o texto em português. Mesmo padrão de
 * TournamentException.
 */
class TicketException extends RuntimeException
{
    /** Evento inexistente, em rascunho ou cancelado. */
    public const EVENT_NOT_FOUND = 'event_not_found';

    /** Fora da janela de vendas do evento. */
    public const SALES_CLOSED = 'sales_closed';

    /** Tipo de ingresso inexistente, inativo ou de outro evento. */
    public const TYPE_NOT_FOUND = 'type_not_found';

    /** O tipo não tem lote à venda agora (esgotado, ou o próximo lote ainda não abriu). */
    public const SOLD_OUT = 'sold_out';

    /** A lotação do evento (somando os tipos) acabou. */
    public const CAPACITY_FULL = 'capacity_full';

    /** O lote vigente deste tipo não é vendido a não sócio. */
    public const MEMBERS_ONLY = 'members_only';

    /** Mais ingressos que o limite por pedido do evento. */
    public const LIMIT_PER_ORDER = 'limit_per_order';

    /** A pessoa já tem ingresso ativo neste evento (um por CPF). */
    public const ALREADY_HAS_TICKET = 'already_has_ticket';

    /** Nome ou CPF do titular inválido, ou CPF repetido no pedido; detalhe em `context`. */
    public const INVALID_HOLDER = 'invalid_holder';

    /** CPF de sócio ativo num pedido sem login: o sócio compra pelo login, com preço de sócio. */
    public const HOLDER_IS_MEMBER = 'holder_is_member';

    /** Pedido de sócio com alguém que não é do título dele (titular e dependentes). */
    public const HOLDER_NOT_IN_TITLE = 'holder_not_in_title';

    /** Sem sócio de título ativo no MultiClubes para o CPF e a matrícula do login. */
    public const MEMBER_NOT_FOUND = 'member_not_found';

    /** Pedido inexistente ou de outra pessoa (token errado). */
    public const ORDER_NOT_FOUND = 'order_not_found';

    /** O pedido não está na situação que a operação pede. */
    public const INVALID_STATUS = 'invalid_status';

    /** O prazo do pedido passou e o estoque acabou nesse meio-tempo: o site estorna. */
    public const HOLD_EXPIRED = 'hold_expired';

    /** Valor pago diferente do total do pedido. */
    public const PAYMENT_MISMATCH = 'payment_mismatch';

    /** Desistência de pedido pago é com a organização, caso a caso. */
    public const PAID_CONTACT_ORGANIZER = 'paid_contact_organizer';

    /** Cupom inexistente, inativo ou fora das datas dele. */
    public const COUPON_INVALID = 'coupon_invalid';

    /** O cupom não vale para nenhum ingresso do pedido (tipo ou público). */
    public const COUPON_NOT_APPLICABLE = 'coupon_not_applicable';

    /** O cupom acabou: os usos que sobram não cobrem o pedido (`context.remaining`). */
    public const COUPON_EXHAUSTED = 'coupon_exhausted';

    /** Ingresso inexistente (código ou CPF sem ingresso no evento). */
    public const TICKET_NOT_FOUND = 'ticket_not_found';

    /** Ingresso já usado na entrada. */
    public const ALREADY_USED = 'already_used';

    /** Ingresso que não está válido para entrar (pendente, cancelado, expirado). */
    public const TICKET_NOT_VALID = 'ticket_not_valid';

    /** Fora da janela de entrada do evento (horas antes do início até o fim). */
    public const OUTSIDE_WINDOW = 'outside_window';

    /** O ingresso é de outro evento que não o escolhido na tela de check-in. */
    public const OTHER_EVENT = 'other_event';

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
