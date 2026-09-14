<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Comando manual de iluminação vindo do Home Assistant (origem típica: Telegram).
 *
 * Quem autoriza é o middleware `api_token` da rota: a API tem token de
 * integração, não usuário.
 */
class StoreHomeAssistantManualCommandRequest extends FormRequest
{
    /**
     * Defaults repetidos na chamada de config().
     *
     * Um deploy que esquece o `config:cache` não enxerga o config/home_assistant.php
     * novo: sem o default aqui, `duration_minutes` viraria 0 e o comando nasceria
     * vencido. Os valores são os mesmos do arquivo de configuração.
     */
    public const DEFAULT_MINUTES = 120;
    public const MAX_MINUTES = 720;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'auto' não liga nem desliga: devolve o contator aos agendamentos e reservas.
            'state'            => ['required', 'in:on,off,auto'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:' . $this->maxMinutes()],
            'origin'           => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'state.in' => 'O estado deve ser on, off ou auto.',
        ];
    }

    /** Duração pedida, ou o padrão de configuração. Null em 'auto'. */
    public function durationMinutes(): ?int
    {
        if ($this->validated('state') === 'auto') {
            return null;
        }

        return $this->integer('duration_minutes') ?: $this->defaultMinutes();
    }

    public function defaultMinutes(): int
    {
        return (int) (config('home_assistant.manual_default_minutes') ?: self::DEFAULT_MINUTES);
    }

    public function maxMinutes(): int
    {
        return (int) (config('home_assistant.manual_max_minutes') ?: self::MAX_MINUTES);
    }
}
