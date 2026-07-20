<?php

namespace App\Services\Poli;

use Illuminate\Support\Facades\Log;

/**
 * Isolated parser for Poli Digital's inbound WhatsApp message payload.
 *
 * Text messages (components.body.text) are confirmed against a real payload.
 * Interactive list/button replies and media messages have not been observed
 * yet, so media detection below is best-effort and logs the raw payload
 * whenever it can't recognize the shape, instead of failing silently.
 */
class PoliMessageParser
{
    /**
     * Candidate keys Poli might use for media components, checked until
     * real media payloads are captured (see plan Fase 0).
     */
    private const MEDIA_COMPONENT_KEYS = ['image', 'media', 'file', 'attachment', 'document'];

    public function isRelevantEvent(array $payload): bool
    {
        $value = $payload['value'] ?? null;

        return ($payload['object'] ?? null) === 'message'
            && ($payload['event'] ?? null) === 'received'
            && is_array($value)
            && ($value['event'] ?? null) === 'MESSAGE'
            && ($value['type'] ?? null) === 'CHAT'
            && ($value['direction'] ?? null) === 'IN';
    }

    public function extractMessageId(array $payload): ?string
    {
        $value = $payload['value'] ?? [];

        return $value['metadata']['external_message_id'] ?? $value['uuid'] ?? null;
    }

    public function parse(array $payload): ?ParsedPoliMessage
    {
        $value = $payload['value'] ?? null;

        if (!is_array($value)) {
            return null;
        }

        $messageId = $this->extractMessageId($payload);
        if ($messageId === null) {
            return null;
        }

        $contact = $value['contact'] ?? $value['author'] ?? [];
        $contactUuid = $contact['uuid'] ?? null;
        $contactPhone = $contact['attributes']['phone'] ?? null;
        $contactName = $contact['attributes']['name'] ?? null;
        $attendanceUuid = $value['attendance']['uuid'] ?? null;

        [$type, $text, $mediaUrl] = $this->parseContent($value['components'] ?? []);

        if ($type === ParsedPoliMessage::TYPE_UNKNOWN) {
            Log::warning('PoliMessageParser: formato de mensagem não reconhecido', [
                'message_id' => $messageId,
                'payload' => $payload,
            ]);
        }

        return new ParsedPoliMessage(
            messageId: $messageId,
            contactUuid: $contactUuid,
            contactPhone: $contactPhone,
            contactName: $contactName,
            attendanceUuid: $attendanceUuid,
            type: $type,
            text: $text,
            mediaUrl: $mediaUrl,
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} [type, text, mediaUrl]
     */
    private function parseContent(array $components): array
    {
        $text = $components['body']['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return [ParsedPoliMessage::TYPE_TEXT, trim($text), null];
        }

        foreach (self::MEDIA_COMPONENT_KEYS as $key) {
            $component = $components[$key] ?? null;
            $url = $this->extractUrl($component);
            if ($url !== null) {
                return [ParsedPoliMessage::TYPE_IMAGE, null, $url];
            }
        }

        return [ParsedPoliMessage::TYPE_UNKNOWN, null, null];
    }

    private function extractUrl(mixed $component): ?string
    {
        if (is_string($component) && str_starts_with($component, 'http')) {
            return $component;
        }

        if (is_array($component)) {
            $url = $component['url'] ?? $component['link'] ?? $component['src'] ?? null;
            if (is_string($url) && str_starts_with($url, 'http')) {
                return $url;
            }
        }

        return null;
    }
}
