<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visitor extends Model
{
    protected $fillable = [
        'full_name',
        'job_title',
        'email',
        'phone',
        'display_message',
        'display_message_removed_at',
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
}
