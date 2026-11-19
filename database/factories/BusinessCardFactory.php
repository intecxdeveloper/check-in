<?php

namespace Database\Factories;

use App\Models\BusinessCard;
use App\Models\BusinessCardScanSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCard>
 */
class BusinessCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scan_session_id' => BusinessCardScanSession::factory(),
            'image_path' => null,
            'image_base64' => base64_encode('RIFF....WEBP'),
            'image_mime' => 'image/webp',
            'full_name' => fake()->name(),
            'job_title' => 'Director',
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0123456789',
            'website' => 'https://example.com',
            'address' => null,
            'ocr_text' => null,
            'notes' => null,
        ];
    }

    /**
     * A freshly snapped photo the desk has not read yet.
     */
    public function unread(): static
    {
        return $this->state(fn (): array => [
            'full_name' => null,
            'job_title' => null,
            'company_name' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => null,
            'ocr_text' => null,
            'ocr_completed_at' => null,
        ]);
    }
}
