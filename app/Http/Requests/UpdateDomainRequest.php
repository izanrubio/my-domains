<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'expiry_source' => ['sometimes', 'nullable', 'in:whois,manual'],
            'auto_renew' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:65535'],
        ];
    }
}
