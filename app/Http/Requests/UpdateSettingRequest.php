<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cloudflare_api_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'expiry_alert_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'alert_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
