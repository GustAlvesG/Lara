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
    ) {}
}
