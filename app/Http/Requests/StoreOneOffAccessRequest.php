<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOneOffAccessRequest extends FormRequest
{
    /**
     * Mesma régua do cadastro de terceirizado: quem pode cadastrar um pode
     * gerar a liberação (hoje, qualquer usuário logado — a rota está no grupo
     * `auth`).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'cpf'    => ['required', 'digits:11', function ($attribute, $value, $fail) {
                if (!self::isValidCpf($value)) {
                    $fail('CPF inválido.');
                }
            }],
            'reason' => ['required', 'string', 'max:1000'],
            // Foto opcional, em data URL (câmera ou importação do formulário).
            'image'  => ['nullable', 'string', 'max:3000000', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'   => 'nome',
            'reason' => 'motivo',
            'image'  => 'foto',
        ];
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$t] !== $d) {
                return false;
            }
        }

        return true;
    }
}
