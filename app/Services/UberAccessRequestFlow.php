<?php

namespace App\Services;

use App\Models\UberAccessRequest;
use App\Services\MultiClubes\MemberTitleValidator;
use App\Services\Poli\ParsedPoliMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * State machine for the "Pedi um Uber" WhatsApp capture flow, keyed by
 * contact_uuid: idle -> aguardando_matricula -> aguardando_nome ->
 * aguardando_local -> aguardando_placa -> aguardando_print ->
 * aguardando_acesso.
 */
class UberAccessRequestFlow
{
    private const TRIGGER_TEXT = 'Carro de Aplicativo';

    /**
     * Tempo máximo sem resposta do associado durante a coleta. Estourado o
     * prazo o pedido vira "expirado" e as respostas atrasadas são ignoradas —
     * só um novo gatilho recomeça o fluxo, do zero.
     */
    public const SESSION_TIMEOUT_SECONDS = 200;

    private const ACCESS_VALIDITY_MINUTES = 30;
    private const PLATE_PATTERN = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    public function __construct(private readonly MemberTitleValidator $memberValidator) {}

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

    /**
     * Só a fase de coleta expira por inatividade. Um pedido já em
     * "aguardando_acesso" está completo e vive até `expires_at` — aplicar o
     * timeout de resposta ali mataria o pedido antes de o motorista chegar.
     */
    private function isExpired(UberAccessRequest $request): bool
    {
        if (!in_array($request->status, UberAccessRequest::CAPTURE_STATUSES, true)) {
            return false;
        }

        if (!$request->last_message_at) {
            return false;
        }

        return $request->last_message_at
            ->copy()
            ->addSeconds(self::SESSION_TIMEOUT_SECONDS)
            ->isPast();
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
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
            'last_message_at' => now(),
        ]);
    }

    private function isTrigger(?string $text): bool
    {
        return $text !== null
            && Str::of($text)->trim()->lower()->startsWith(Str::lower(self::TRIGGER_TEXT));
    }

    private function advance(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        return match ($request->status) {
            UberAccessRequest::STATUS_AGUARDANDO_MATRICULA => $this->captureText(
                $request,
                $message,
                'matricula',
                UberAccessRequest::STATUS_AGUARDANDO_NOME
            ),
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

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $message->text));

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

        // Com todos os dados em mãos, confere nome + matrícula no MultiClubes.
        // O resultado é registrado para a portaria ver; não bloqueia o pedido.
        $validation = $this->memberValidator->validate($request->matricula, $request->requester_name);

        // O pedido está completo, mas o acesso ainda não aconteceu: fica
        // "aguardando acesso do motorista" até ele chegar na portaria (quando
        // vira "concluido") ou a validade vencer (quando vira "expirado").
        $request->update([
            'screenshot_url' => $message->mediaUrl,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_ACESSO,
            'member_validation' => $validation->status,
            'member_validation_name' => $validation->matchedName,
            'member_validated_at' => $completedAt,
            'completed_at' => $completedAt,
            'expires_at' => $completedAt->copy()->addMinutes(self::ACCESS_VALIDITY_MINUTES),
            'last_message_at' => $completedAt,
        ]);

        if ($validation->status !== UberAccessRequest::MEMBER_VALIDATION_VALIDADO) {
            Log::info('UberAccessRequestFlow: pedido concluído sem confirmar o sócio', [
                'uber_access_request_id' => $request->id,
                'member_validation' => $validation->status,
                'matricula' => $request->matricula,
            ]);
        }

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
