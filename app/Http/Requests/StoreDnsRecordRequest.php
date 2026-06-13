<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDnsRecordRequest extends FormRequest
{
    private const SUPPORTED_TYPES = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'PTR'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:' . implode(',', self::SUPPORTED_TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', $this->contentRule()],
            'ttl' => ['sometimes', 'integer', 'min:1'],
            'proxied' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function contentRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $type = $this->input('type');

            if ($type === 'A' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $fail('Content for A records must be a valid IPv4 address.');
                return;
            }

            if ($type === 'AAAA' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $fail('Content for AAAA records must be a valid IPv6 address.');
                return;
            }

            if (in_array($type, ['CNAME', 'NS', 'MX', 'PTR'], true)
                && !preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]{0,253}[a-zA-Z0-9])?\.?$/', $value)
            ) {
                $fail("Content for {$type} records must be a valid hostname.");
            }
        };
    }
}
