<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskLaraRequest extends FormRequest
{
    /**
     * O controle de acesso é da rota (`permission:use lara chat`).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mensagem = $this->input('mensagem');

        if (is_string($mensagem)) {
            $this->merge(['mensagem' => trim($mensagem)]);
        }
    }

    /**
     * O limite de caracteres também é aplicado no cliente da IA (que trunca
     * antes de enviar). Aqui ele existe para o funcionário receber um aviso
     * claro em vez de ter a pergunta cortada em silêncio.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'mensagem' => ['required', 'string', 'min:2', 'max:' . (int) config('services.lara.max_input_chars', 1000)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mensagem.required' => 'Escreva uma pergunta.',
            'mensagem.min' => 'A pergunta está curta demais.',
            'mensagem.max' => 'A pergunta é longa demais — tente resumir.',
        ];
    }
}
