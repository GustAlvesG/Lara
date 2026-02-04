<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Importante: deve ser true para permitir a requisição
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|min:3|max:100',
            'email'   => 'required|email',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10',
            'type'    => 'required|string',
            // 'data'   => 'sometimes|array',
        ];
    }
    
    // Opcional: Mensagens personalizadas em PT-BR
    public function messages(): array
    {
        return [
            // 'email.required' => 'O campo e-mail é obrigatório.',
            // 'email.email'    => 'Por favor, insira um e-mail válido.',
            // ... outros campos
        ];
    }
}