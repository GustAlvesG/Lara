<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados e conteúdo da campanha do Disparo de E-mails. O remetente sai da
 * lista de `email_campaigns.from_addresses`: endereço fora dela não tem
 * SPF/DKIM no provedor e o e-mail cairia no spam.
 */
class EmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            // O remetente que a campanha já tem vale, mesmo que tenha saído da configuração.
            'from_address' => ['required', 'email', Rule::in(array_filter(array_merge(
                config('email_campaigns.from_addresses'),
                [$this->route('campaign')?->from_address],
            )))],
            'from_name' => ['nullable', 'string', 'max:100'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'editor' => ['required', 'in:visual,html'],
            'body' => ['nullable', 'string', 'max:1000000'],
            'html' => ['nullable', 'string', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome da campanha',
            'subject' => 'assunto',
            'from_address' => 'e-mail do remetente',
            'from_name' => 'nome do remetente',
            'reply_to' => 'responder para',
            'body' => 'texto do e-mail',
            'html' => 'conteúdo',
        ];
    }

    public function messages(): array
    {
        return [
            'from_address.in' => 'Escolha um dos remetentes da lista.',
            'body.max' => 'O texto passou de 1 MB. Imagens coladas no texto pesam muito: envie pelo botão Imagem.',
            'html.max' => 'O conteúdo passou de 1 MB. Diminua o HTML (imagens em base64 pesam muito: envie pelo botão Imagem).',
        ];
    }
}
