<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
            'other_interest' => $this->selectedOtherService($services) && filled($this->input('other_interest'))
                ? trim((string) $this->input('other_interest'))
                : null,
        ]);

        $this->hydrateAvatarFromBase64();
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:1', 'max:120', 'not_regex:/[<>]/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'display_message' => ['nullable', 'string', 'max:80'],
            'avatar_seed' => ['nullable', 'string', 'max:80'],
            'avatar' => ['nullable', 'file', 'max:6144', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=8000,max_height=8000'],
            'company_name' => ['required', 'string', 'max:180'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'public_website' => ['nullable', 'url:http,https', 'max:255'],
            'public_display_enabled' => ['required', 'boolean'],
            'follow_up_consent' => ['required', 'boolean'],
            'privacy_accepted' => ['accepted'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'other_interest' => [
                Rule::requiredIf(fn (): bool => $this->selectedOtherService()),
                'nullable',
                'string',
                'max:80',
                'not_regex:/[<>]/',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('email') && ! $this->filled('phone')) {
                $validator->errors()->add('email', 'Email or phone is enough — either one is fine.');
            }

            if ($this->boolean('avatar_invalid')) {
                $validator->errors()->add('avatar', 'Please upload a JPG, PNG or WebP photo.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'privacy_accepted.accepted' => 'A quick privacy tick keeps everyone comfortable.',
            'services.required' => 'Pick at least one interest — any is fine.',
            'services.min' => 'Pick at least one interest — any is fine.',
            'other_interest.required' => 'Type the interest you have in mind.',
            'other_interest.required_if' => 'Type the interest you have in mind.',
            'other_interest.not_regex' => 'Keep that interest as plain text.',
            'avatar.image' => 'Please upload a photo or logo image.',
            'avatar.max' => 'Keep uploads under 6MB — a phone photo is perfect.',
            'avatar.mimes' => 'Please upload a JPG, PNG or WebP photo.',
        ];
    }

    /**
     * @param  list<int>|null  $serviceIds
     */
    private function selectedOtherService(?array $serviceIds = null): bool
    {
        $otherId = Service::query()->where('slug', 'other')->value('id');

        if ($otherId === null) {
            return false;
        }

        $ids = array_map('intval', $serviceIds ?? $this->input('services', []));

        return in_array((int) $otherId, $ids, true);
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Accept a data-URI or raw base64 image and turn it into an uploaded file
     * so existing size/MIME rules apply without multipart.
     */
    private function hydrateAvatarFromBase64(): void
    {
        $avatar = $this->input('avatar');
        if (! is_string($avatar)) {
            return;
        }

        $avatar = trim($avatar);
        if ($avatar === '') {
            $this->merge(['avatar' => null]);

            return;
        }

        $file = $this->uploadedFileFromBase64($avatar);
        if ($file === null) {
            $this->merge([
                'avatar' => null,
                'avatar_invalid' => true,
            ]);

            return;
        }

        $this->files->set('avatar', $file);
        $this->merge(['avatar' => null]);
    }

    private function uploadedFileFromBase64(string $value): ?UploadedFile
    {
        if (preg_match('/^data:image\/(?:jpeg|jpg|png|webp);base64,(.+)$/i', $value, $matches) === 1) {
            $value = $matches[1];
        }

        $value = preg_replace('/\s+/', '', $value) ?? '';
        if ($value === '' || strlen($value) > 8_400_000) {
            return null;
        }

        $binary = base64_decode($value, true);
        if ($binary === false || $binary === '' || strlen($binary) > 6 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            return null;
        }

        $map = [
            IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
            IMAGETYPE_PNG => ['png', 'image/png'],
            IMAGETYPE_WEBP => ['webp', 'image/webp'],
        ];

        $type = $info[2] ?? null;
        if (! isset($map[$type])) {
            return null;
        }

        [$extension, $mime] = $map[$type];
        $path = tempnam(sys_get_temp_dir(), 'avt');
        if ($path === false || file_put_contents($path, $binary) === false) {
            return null;
        }

        return new UploadedFile($path, 'avatar.'.$extension, $mime, UPLOAD_ERR_OK, true);
    }
}
