<?php

namespace App\Services\Poli;

use Illuminate\Support\Facades\Log;

/**
 * Isolated parser for Poli Digital's inbound WhatsApp message payload.
 *
 * Both text (value.type == CHAT, components.body.text) and image
 * (value.type == IMAGE, components.attachments[].media.url) shapes are
 * confirmed against real payloads. Anything else logs the raw payload
 * instead of failing silently, so unseen shapes stay visible.
 */
class PoliMessageParser
{
    /**
     * value.type values we handle: CHAT (text) and IMAGE (the print).
     */
    private const RELEVANT_TYPES = ['CHAT', 'IMAGE'];

    /**
     * Best-effort fallback keys for media shapes not yet observed, checked
     * only after the confirmed components.attachments[] structure misses.
     */
    private const MEDIA_COMPONENT_KEYS = ['image', 'media', 'file', 'attachment', 'document'];

    public function isRelevantEvent(array $payload): bool
    {
        $value = $payload['value'] ?? null;

        return ($payload['object'] ?? null) === 'message'
            && ($payload['event'] ?? null) === 'received'
            && is_array($value)
            && ($value['event'] ?? null) === 'MESSAGE'
            && in_array($value['type'] ?? null, self::RELEVANT_TYPES, true)
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
        if (is_string($text)) {
            $text = $this->sanitizeText($text);
            if ($text !== '') {
                return [ParsedPoliMessage::TYPE_TEXT, $text, null];
            }
        }

        $mediaUrl = $this->extractImageUrl($components);
        if ($mediaUrl !== null) {
            return [ParsedPoliMessage::TYPE_IMAGE, null, $mediaUrl];
        }

        return [ParsedPoliMessage::TYPE_UNKNOWN, null, null];
    }

    /**
     * Confirmed Poli image shape:
     *   components.attachments[] => { type: "image", media: { url }, mime_type }
     * Falls back to best-effort keys for shapes not yet observed.
     */
    private function extractImageUrl(array $components): ?string
    {
        foreach ($components['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $isImage = ($attachment['type'] ?? null) === 'image'
                || str_starts_with((string) ($attachment['mime_type'] ?? ''), 'image/');
            $url = $attachment['media']['url'] ?? null;

            if ($isImage && is_string($url) && $url !== '') {
                return $this->normalizeUrl($url);
            }
        }

        foreach (self::MEDIA_COMPONENT_KEYS as $key) {
            $url = $this->extractUrl($components[$key] ?? null);
            if ($url !== null) {
                return $this->normalizeUrl($url);
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        return str_replace('\\/', '/', trim($url));
    }

    /**
     * Poli occasionally delivers text with leftover JSON-escaping artifacts
     * (e.g. "Pedi um Uber\/99\/Taxi\n}"), likely from a double-encoding step
     * upstream. Unescape known sequences and strip the residual noise.
     */
    private function sanitizeText(string $text): string
    {
        $text = str_replace(['\\/', '\\n', '\\r', '\\t'], ['/', "\n", "\r", "\t"], $text);
        $text = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);
        $text = trim($text, " \t\n\r\0\x0B{}\"'");

        return trim(preg_replace('/\s+/', ' ', $text));
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
