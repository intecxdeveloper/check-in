<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'display_message' => ['nullable', 'string', 'max:80'],
            'company_name' => ['required', 'string', 'max:180'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'public_website' => ['nullable', 'url', 'max:255'],
            'public_display_enabled' => ['required', 'boolean'],
            'follow_up_consent' => ['required', 'boolean'],
            'privacy_accepted' => ['accepted'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('email') && ! $this->filled('phone')) {
                $validator->errors()->add('email', 'Please provide an email or phone number.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'privacy_accepted.accepted' => 'Please confirm you have read the privacy notice.',
            'services.required' => 'Select at least one interest.',
            'services.min' => 'Select at least one interest.',
        ];
    }
}
