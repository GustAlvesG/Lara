<?php

namespace App\Http\Requests;

class SearchInformationRequest extends KeywordSearchRequest
{
    protected function extraRules(): array
    {
        return [
            'all_versions' => ['nullable', 'boolean'],
        ];
    }
}
