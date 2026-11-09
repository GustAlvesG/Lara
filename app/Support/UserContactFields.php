<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CPF e telefone do usuário do Lara — as mesmas regras no registro, no perfil
 * e na tela de Usuários (09/11/2026).
 *
 * O CPF é o que sai impresso quando o usuário assina um documento (Diretoria
 * ou "usuário do Lara") e o que o gov.br confere; o telefone é o do aviso de
 * WhatsApp e o contato do signatário. Os dois são gravados só com dígitos.
 */
final class UserContactFields
{
    /** Tira a máscara antes de validar: a regra de tamanho olha os dígitos. */
    public static function normalize(Request $request): void
    {
        $dados = [];

        if ($request->has('cpf')) {
            $dados['cpf'] = Cpf::digits((string) $request->input('cpf')) ?: null;
        }

        if ($request->has('phone')) {
            $dados['phone'] = Phone::digits((string) $request->input('phone')) ?: null;
        }

        if ($dados !== []) {
            $request->merge($dados);
        }
    }

    /**
     * @return array<int, mixed>
     */
    public static function cpfRules(?User $user = null, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'digits:11',
            Rule::unique('users', 'cpf')->ignore($user?->id),
            function ($attribute, $value, $fail) {
                if (!Cpf::isValid($value)) {
                    $fail('CPF inválido.');
                }
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function phoneRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            function ($attribute, $value, $fail) {
                if (!Phone::isValid($value)) {
                    $fail('Telefone inválido: informe o DDD e o número.');
                }
            },
        ];
    }

    /**
     * O que falta no cadastro desta pessoa, na voz da tela ("CPF", "telefone").
     *
     * @return array<int, string>
     */
    public static function missing(User $user): array
    {
        return array_values(array_filter([
            blank($user->cpf) ? 'CPF' : null,
            blank($user->email) ? 'e-mail' : null,
            blank($user->phone) ? 'telefone' : null,
        ]));
    }
}
