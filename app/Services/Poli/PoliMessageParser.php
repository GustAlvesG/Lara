<?php

namespace App\Services\Poli;

use Illuminate\Support\Carbon;
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

    /**
     * Evento de SAÍDA que leva um menu de opções.
     *
     * Repare que o evento é "sent", e não "received": por isso `isRelevantEvent`
     * continua ignorando estes payloads, e o fluxo do WhatsApp não muda. Eles
     * servem só para indexar o menu e, depois, conferir de onde veio o toque.
     */
    public function isOutgoingListMessage(array $payload): bool
    {
        $value = $payload['value'] ?? null;

        return ($payload['object'] ?? null) === 'message'
            && ($payload['event'] ?? null) === 'sent'
            && is_array($value)
            && ($value['direction'] ?? null) === 'OUT'
            && $this->listRows($value['components'] ?? []) !== [];
    }

    /**
     * Os dados do menu enviado, no formato de poli_list_messages.
     *
     * A chave é `value.uuid` — é ele que a resposta devolve em
     * `value.context.message.uuid`. O wamid de `metadata.external_message_id`
     * existe aqui também, mas o contexto da resposta não o cita.
     *
     * @return array<string, mixed>|null
     */
    public function parseOutgoingList(array $payload): ?array
    {
        $value = $payload['value'] ?? [];
        $uuid = $value['uuid'] ?? null;

        if (!is_string($uuid) || $uuid === '') {
            return null;
        }

        $rows = $this->listRows($value['components'] ?? []);

        if ($rows === []) {
            return null;
        }

        $timestamp = $value['timestamp'] ?? null;

        return [
            'poli_message_uuid' => $uuid,
            'attendance_uuid' => $value['attendance']['uuid'] ?? null,
            'contact_uuid' => $value['contact']['uuid'] ?? null,
            'template_name' => $value['components']['name'] ?? null,
            'rows' => $rows,
            'sent_at' => is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null,
        ];
    }

    /**
     * As opções da lista, achatadas: a seção não interessa, só o par
     * título/descrição de cada linha — é ele que a resposta reproduz.
     *
     * O `id` da linha é deliberadamente descartado: é identidade gerada pela
     * Poli e muda se alguém recriar o menu, então nenhuma regra pode depender
     * dele.
     *
     * @return list<array{title: string, description: string|null}>
     */
    private function listRows(array $components): array
    {
        $rows = [];

        foreach ($components['section'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach ($section['rows'] ?? [] as $row) {
                $title = $row['messageOption']['title'] ?? null;

                if (!is_string($title) || trim($title) === '') {
                    continue;
                }

                $description = $row['messageOption']['description'] ?? null;

                $rows[] = [
                    'title' => $this->sanitizeText($title),
                    'description' => is_string($description) ? $this->sanitizeText($description) : null,
                ];
            }
        }

        return $rows;
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
            // Só existe na ENTRADA. Na saída, `value.context` guarda a
            // definição da própria lista — mesmo nome, outra coisa.
            contextMessageUuid: $value['context']['message']['uuid'] ?? null,
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
