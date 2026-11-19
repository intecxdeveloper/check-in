<?php

namespace Database\Factories;

use App\Models\BusinessCardScanSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCardScanSession>
 */
class BusinessCardScanSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => BusinessCardScanSession::hashToken('scan-token-placeholder-32chars!!'),
            'expires_at' => now()->addHours(BusinessCardScanSession::LIFETIME_HOURS),
            'revoked_at' => null,
            'last_seen_at' => null,
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => BusinessCardScanSession::hashToken($token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
