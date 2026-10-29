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

    protected function prepareForValidation(): void
    {
        $services = $this->input('services');
        if (is_string($services)) {
            $services = array_filter(array_map('intval', explode(',', $services)));
        } elseif (! is_array($services)) {
            $services = [];
        } else {
            $services = array_values(array_filter(array_map('intval', $services)));
        }

        $this->merge([
            'public_display_enabled' => $this->toBool($this->input('public_display_enabled'), true),
            'follow_up_consent' => $this->toBool($this->input('follow_up_consent'), false),
            'privacy_accepted' => $this->toBool($this->input('privacy_accepted'), false) ? '1' : '0',
            'services' => $services,
            'avatar_seed' => filled($this->input('avatar_seed'))
                ? trim((string) $this->input('avatar_seed'))
                : null,
            'public_website' => filled($this->input('public_website'))
                ? trim((string) $this->input('public_website'))
                : null,
            'job_title' => filled($this->input('job_title')) ? trim((string) $this->input('job_title')) : null,
            'email' => filled($this->input('email')) ? trim((string) $this->input('email')) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'display_message' => filled($this->input('display_message')) ? trim((string) $this->input('display_message')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'display_message' => ['nullable', 'string', 'max:80'],
            'avatar_seed' => ['nullable', 'string', 'max:80'],
            'avatar' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp,gif'],
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
                $validator->errors()->add('email', 'Email or phone is enough — either one is fine.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'privacy_accepted.accepted' => 'A quick privacy tick keeps everyone comfortable.',
            'services.required' => 'Pick at least one interest — any is fine.',
            'services.min' => 'Pick at least one interest — any is fine.',
            'avatar.image' => 'Please upload a photo or logo image.',
            'avatar.max' => 'Keep uploads under 4MB — a phone photo is perfect.',
        ];
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
