<?php

namespace App\Services\Poli;

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
    ) {}
}
