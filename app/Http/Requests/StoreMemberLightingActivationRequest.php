<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Acionamento de luz pedido pelo sócio no aplicativo de reservas.
 *
 * Quem autoriza são os middlewares da rota (`api_token` + `login_token`); aqui
 * só se valida quanto tempo foi pedido. O piso e o teto vêm de
 * `config/home_assistant.php` para que mudá-los não exija deploy do app.
 */
class StoreMemberLightingActivationRequest extends FormRequest
{
    /**
     * Defaults repetidos na chamada de config().
     *
     * Um deploy que esquece o `config:cache` não enxerga o
     * config/home_assistant.php novo: sem o default aqui, o teto viraria 0 e
     * nenhum acionamento passaria na validação. Mesmos valores do arquivo.
     */
    public const DEFAULT_MAX_MINUTES = 120;
    public const DEFAULT_MIN_MINUTES = 15;

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
            // Ausente vale o teto: é o pedido mais comum, e a tela que ainda
            // não tiver o seletor continua funcionando.
            'minutes' => ['nullable', 'integer', 'min:' . $this->minMinutes(), 'max:' . $this->maxMinutes()],
        ];
    }

    public function messages(): array
    {
        return [
            'minutes.min' => 'O tempo mínimo é de ' . $this->minMinutes() . ' minutos.',
            'minutes.max' => 'O tempo máximo por acionamento é de ' . $this->maxMinutes() . ' minutos.',
        ];
    }

    /** Minutos pedidos, ou null para o serviço aplicar o teto. */
    public function minutes(): ?int
    {
        return $this->integer('minutes') ?: null;
    }

    public function maxMinutes(): int
    {
        return (int) (config('home_assistant.self_service.max_minutes') ?: self::DEFAULT_MAX_MINUTES);
    }

    public function minMinutes(): int
    {
        return (int) (config('home_assistant.self_service.min_minutes') ?: self::DEFAULT_MIN_MINUTES);
    }
}
