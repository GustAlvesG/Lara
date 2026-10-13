<?php

namespace App\Http\Requests\Replay;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aviso de clipe novo pelo sistema de captura.
 *
 * O arquivo não vem na requisição: o sistema de captura já o gravou no
 * servidor de arquivos (FTP) e informa só o caminho.
 *
 * A autorização é do middleware (Sanctum + ability `replay:operate`), por
 * isso `authorize()` devolve true: quem chegou até aqui já provou quem é.
 */
class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Caminho do clipe no FTP, relativo à pasta do Replay
            // (REPLAY_FTP_ROOT) — com ou sem ela na frente. 255 é o tamanho
            // da coluna `file_path`.
            'path' => ['required', 'string', 'max:255'],
            // ISO 8601. É o instante do APERTO DO BOTÃO (fim do clipe), e é
            // dele que sai tanto o vínculo com a reserva quanto a expiração.
            'recorded_at' => ['required', 'date'],
            // A faixa vai além dos 60s do cadastro de propósito: o clipe real
            // costuma sair com um ou dois segundos a mais por causa do corte
            // no quadro-chave, e recusar o vídeo por isso seria perder a
            // jogada.
            'duration_seconds' => ['required', 'integer', 'between:1,120'],
            // Id do clipe no sistema de captura. Opcional, mas é o que dá
            // idempotência ao reenvio junto com o caminho.
            'external_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Envie em path o caminho do clipe no FTP.',
            'recorded_at.date' => 'Envie recorded_at em ISO 8601 (ex.: 2026-09-17T14:32:10-03:00).',
        ];
    }
}
