<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state_region' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(config('countries', []))],
            'website_url' => ['nullable', 'url', 'max:255'],
            'website_url_2' => ['nullable', 'url', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['website_url', 'website_url_2'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && $value !== '' && ! preg_match('#^https?://#i', $value)) {
                $merge[$field] = 'https://'.$value;
            }
        }

        if ($merge) {
            $this->merge($merge);
        }
    }
}
