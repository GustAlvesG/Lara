<?php

namespace App\Http\Requests\Replay;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envio de um clipe pelo sistema de captura.
 *
 * A autorização é do middleware (Sanctum + ability `replay:operate`), por
 * isso `authorize()` devolve true: quem chegou até aqui já provou quem é.
 */
class StoreVideoRequest extends FormRequest
{
    /** Teto do arquivo, em kilobytes. 256MB cobre 60s a 1080p com folga. */
    const MAX_KILOBYTES = 262144;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:' . self::MAX_KILOBYTES],
            // ISO 8601. É o instante do APERTO DO BOTÃO (fim do clipe), e é
            // dele que sai tanto o vínculo com a reserva quanto a expiração.
            'recorded_at' => ['required', 'date'],
            // A faixa vai além dos 60s do cadastro de propósito: o clipe real
            // costuma sair com um ou dois segundos a mais por causa do corte
            // no quadro-chave, e recusar o vídeo por isso seria perder a
            // jogada.
            'duration_seconds' => ['required', 'integer', 'between:1,120'],
            // Id do clipe no sistema de captura. Opcional, mas sem ele não há
            // idempotência: um reenvio vira um segundo vídeo.
            'external_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Arquivo acima do limite de 256MB.',
            'recorded_at.date' => 'Envie recorded_at em ISO 8601 (ex.: 2026-09-17T14:32:10-03:00).',
        ];
    }
}
