<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParkingAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'           => ['required', 'string', 'max:20'],
            'name'            => ['required', 'string', 'max:255'],
            'expiration_date' => ['required', 'date'],
        ];
    }
}
