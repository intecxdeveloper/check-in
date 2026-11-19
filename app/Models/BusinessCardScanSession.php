<?php

namespace App\Models;

use Database\Factories\BusinessCardScanSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessCardScanSession extends Model
{
    /** @use HasFactory<BusinessCardScanSessionFactory> */
    use HasFactory;

    public const TOKEN_BYTES = 32;

    public const LIFETIME_HOURS = 12;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessCards(): HasMany
    {
        return $this->hasMany(BusinessCard::class, 'scan_session_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at?->isFuture() === true;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function generateToken(): string
    {
        return Str::lower(Str::random(self::TOKEN_BYTES));
    }

    public static function findActive(string $token): ?self
    {
        $token = Str::lower($token);

        if (! preg_match('/^[a-z0-9]{'.self::TOKEN_BYTES.'}$/', $token)) {
            return null;
        }

        $session = self::query()
            ->where('token_hash', self::hashToken($token))
            ->first();

        if ($session === null || ! $session->isUsable()) {
            return null;
        }

        return $session;
    }

    /**
     * @return array{0: self, 1: string}
     */
    public static function issueFor(User $user): array
    {
        self::query()
            ->whereBelongsTo($user)
            ->usable()
            ->update(['revoked_at' => now()]);

        $token = self::generateToken();

        $session = self::query()->create([
            'user_id' => $user->id,
            'token_hash' => self::hashToken($token),
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);

        return [$session, $token];
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }
}
