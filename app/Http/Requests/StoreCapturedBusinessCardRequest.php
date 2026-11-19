<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapturedBusinessCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required_without:image_base64', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'image_base64' => ['required_without:image', 'nullable', 'string', 'max:8000000'],
        ];
    }
}
