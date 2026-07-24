<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'front_image' => ['required', 'image', 'max:5120'],
            'back_image' => ['required', 'image', 'max:5120'],
            'layout' => ['required', 'json'],
            'is_active' => ['nullable', 'boolean'],
            'card_width_mm' => ['nullable', 'numeric', 'min:1'],
            'card_height_mm' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    /**
     * Garante que o JSON de layout tem as 6 caixas obrigatórias com x/y/w/h numéricos,
     * evitando persistir um payload malformado vindo de um bug no editor JS.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $layout = json_decode((string) $this->input('layout'), true);

            if (!is_array($layout)) {
                return;
            }

            $required = [
                'front.photo', 'front.name', 'front.role',
                'back.name', 'back.admission_date', 'back.registration_number',
            ];

            foreach ($required as $path) {
                [$side, $key] = explode('.', $path);
                $field = $layout[$side][$key] ?? null;

                if (!is_array($field) || !isset($field['x'], $field['y'], $field['w'], $field['h'])
                    || !is_numeric($field['x']) || !is_numeric($field['y'])
                    || !is_numeric($field['w']) || !is_numeric($field['h'])) {
                    $validator->errors()->add('layout', "Posição do campo \"{$path}\" está ausente ou inválida.");
                }
            }
        });
    }
}
