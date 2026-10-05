<?php

namespace App\Services\Poli;

use Carbon\CarbonInterface;

class ParsedPoliMessage
{
    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $messageId,
        public readonly ?string $contactUuid,
        public readonly ?string $contactPhone,
        public readonly ?string $contactName,
        public readonly ?string $attendanceUuid,
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $mediaUrl = null,
        /**
         * O `value.uuid` da mensagem a que esta responde — preenchido quando o
         * associado TOCA numa opção de menu, e nulo quando ele digita.
         *
         * É o que liga a resposta ao menu indexado em poli_list_messages, e a
         * única forma de saber se o "Carro de Aplicativo" que chegou veio do
         * botão ou do teclado.
         */
        public readonly ?string $contextMessageUuid = null,
        /**
         * `value.attendance.type` (INITIATED_BY_CONTACT / INITIATED_BY_BUSINESS)
         * e `value.attendance.status` (null, IN_PROGRESS, CLOSED…).
         */
        public readonly ?string $attendanceType = null,
        public readonly ?string $attendanceStatus = null,
        /**
         * `value.attendance.attendant.uuid`: quem está com o atendimento. É a
         * regra de dono — a conversa é da Lara quando é o usuário O Lara e o
         * atendimento não está encerrado. Nulo = ninguém (fase do bot da Poli).
         */
        public readonly ?string $attendanceAttendantUuid = null,
        public readonly ?string $attendanceClosedReason = null,
        /**
         * Headers do webhook (`X-Webhook-Attempt`, `X-Webhook-Delivery-Id`).
         * Um evento reenviado pode trazer o estado do atendimento do momento
         * da entrega original — acima de 1, o dono é conferido na API.
         */
        public readonly ?int $webhookAttempt = null,
        public readonly ?string $webhookDeliveryId = null,
        /**
         * Quando a Poli criou a mensagem (`value.metadata.created_at`) — a
         * hora em que o contato escreveu, não a em que ela chegou aqui. É o
         * que separa a resposta a uma pergunta do que foi escrito antes de
         * ela aparecer na tela. Nulo quando o evento não traz a hora.
         */
        public readonly ?CarbonInterface $createdAt = null,
    ) {}

    public function isClosed(): bool
    {
        return $this->attendanceStatus === 'CLOSED';
    }

    public function isRetry(): bool
    {
        return $this->webhookAttempt !== null && $this->webhookAttempt > 1;
    }

    /** A mesma mensagem, com o atendimento como a API o descreve agora. */
    public function withAttendance(?string $attendantUuid, ?string $status): static
    {
        return new static(
            messageId: $this->messageId,
            contactUuid: $this->contactUuid,
            contactPhone: $this->contactPhone,
            contactName: $this->contactName,
            attendanceUuid: $this->attendanceUuid,
            type: $this->type,
            text: $this->text,
            mediaUrl: $this->mediaUrl,
            contextMessageUuid: $this->contextMessageUuid,
            attendanceType: $this->attendanceType,
            attendanceStatus: $status,
            attendanceAttendantUuid: $attendantUuid,
            attendanceClosedReason: $this->attendanceClosedReason,
            webhookAttempt: $this->webhookAttempt,
            webhookDeliveryId: $this->webhookDeliveryId,
            createdAt: $this->createdAt,
        );
    }
}
