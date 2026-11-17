<?php

namespace App\Models;

use App\Support\GiftWheel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Visitor extends Model
{
    protected $fillable = [
        'full_name',
        'job_title',
        'email',
        'phone',
        'display_message',
        'other_interest',
        'display_message_removed_at',
        'gift_prize',
        'gift_spun_at',
        'avatar_seed',
        'avatar_path',
        'company_id',
        'follow_up_consent_at',
        'privacy_notice_version',
        'submission_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_consent_at' => 'datetime',
            'display_message_removed_at' => 'datetime',
            'gift_spun_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    #[Scope]
    protected function withPublicMessage(Builder $query): Builder
    {
        return $query
            ->whereNotNull('display_message')
            ->where('display_message', '!=', '');
    }

    public function hasPublicMessage(): bool
    {
        return filled($this->display_message);
    }

    /**
     * Short counter code staff can match on the visitor's phone receipt.
     */
    public function claimCode(): string
    {
        return 'CK-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function firstName(): ?string
    {
        $firstName = trim(explode(' ', trim((string) $this->full_name))[0] ?? '');

        return $firstName !== '' ? $firstName : null;
    }

    public function giftPrizeLabel(): ?string
    {
        if (! filled($this->gift_prize)) {
            return null;
        }

        $prize = GiftWheel::find((string) $this->gift_prize);

        return $prize['label'] ?? Str::headline((string) $this->gift_prize);
    }

    public static function findByClaimCode(string $code): ?self
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $code));
        if (! preg_match('/^CK-?(\d{1,8})$/', $normalized, $matches)) {
            return null;
        }

        return self::query()->find((int) $matches[1]);
    }
}
