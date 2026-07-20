<?php

namespace App\Services;

use App\Models\UberAccessRequest;
use App\Services\Poli\ParsedPoliMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * State machine for the "Pedi um Uber" WhatsApp capture flow, keyed by
 * contact_uuid: idle -> aguardando_nome -> aguardando_local ->
 * aguardando_placa -> aguardando_print -> concluido.
 */
class UberAccessRequestFlow
{
    private const TRIGGER_TEXT = 'pedi um uber';
    private const SESSION_TIMEOUT_MINUTES = 30;
    private const ACCESS_VALIDITY_MINUTES = 30;
    private const PLATE_PATTERN = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    public function handle(ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->contactUuid === null) {
            return null;
        }

        $request = UberAccessRequest::open()
            ->where('contact_uuid', $message->contactUuid)
            ->latest('id')
            ->first();

        if ($request && $this->isExpired($request)) {
            $request->update(['status' => UberAccessRequest::STATUS_EXPIRADO]);
            $request = null;
        }

        return $request
            ? $this->advance($request, $message)
            : $this->maybeStartSession($message);
    }

    private function isExpired(UberAccessRequest $request): bool
    {
        if (!$request->last_message_at) {
            return false;
        }

        return $request->last_message_at->diffInMinutes(now()) > self::SESSION_TIMEOUT_MINUTES;
    }

    private function maybeStartSession(ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT || !$this->isTrigger($message->text)) {
            return null;
        }

        return UberAccessRequest::create([
            'contact_uuid' => $message->contactUuid,
            'contact_phone' => $message->contactPhone,
            'contact_name_whatsapp' => $message->contactName,
            'poli_attendance_uuid' => $message->attendanceUuid,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_NOME,
            'last_message_at' => now(),
        ]);
    }

    private function isTrigger(?string $text): bool
    {
        return $text !== null && Str::of($text)->trim()->lower()->is(self::TRIGGER_TEXT);
    }

    private function advance(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        return match ($request->status) {
            UberAccessRequest::STATUS_AGUARDANDO_NOME => $this->captureText(
                $request,
                $message,
                'requester_name',
                UberAccessRequest::STATUS_AGUARDANDO_LOCAL
            ),
            UberAccessRequest::STATUS_AGUARDANDO_LOCAL => $this->captureText(
                $request,
                $message,
                'club_location',
                UberAccessRequest::STATUS_AGUARDANDO_PLACA
            ),
            UberAccessRequest::STATUS_AGUARDANDO_PLACA => $this->capturePlate($request, $message),
            UberAccessRequest::STATUS_AGUARDANDO_PRINT => $this->captureScreenshot($request, $message),
            default => $this->ignore($request, $message),
        };
    }

    private function captureText(
        UberAccessRequest $request,
        ParsedPoliMessage $message,
        string $field,
        string $nextStatus
    ): ?UberAccessRequest {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT) {
            return $this->ignore($request, $message);
        }

        $request->update([
            $field => $message->text,
            'status' => $nextStatus,
            'last_message_at' => now(),
        ]);

        return $request;
    }

    private function capturePlate(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT) {
            return $this->ignore($request, $message);
        }

        $plate = strtoupper(str_replace([' ', '-'], '', $message->text));

        if (!preg_match(self::PLATE_PATTERN, $plate)) {
            Log::warning('UberAccessRequestFlow: placa fora do formato esperado', [
                'uber_access_request_id' => $request->id,
                'value' => $message->text,
            ]);
        }

        $request->update([
            'vehicle_plate' => $plate,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_PRINT,
            'last_message_at' => now(),
        ]);

        return $request;
    }

    private function captureScreenshot(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_IMAGE) {
            return $this->ignore($request, $message);
        }

        $completedAt = now();

        $request->update([
            'screenshot_url' => $message->mediaUrl,
            'status' => UberAccessRequest::STATUS_CONCLUIDO,
            'completed_at' => $completedAt,
            'expires_at' => $completedAt->copy()->addMinutes(self::ACCESS_VALIDITY_MINUTES),
            'last_message_at' => $completedAt,
        ]);

        return $request;
    }

    private function ignore(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        Log::info('UberAccessRequestFlow: mensagem fora de ordem ignorada', [
            'uber_access_request_id' => $request->id,
            'status' => $request->status,
            'message_type' => $message->type,
        ]);

        return null;
    }
}
