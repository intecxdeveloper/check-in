<?php

namespace App\Models;

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
        'company_id',
        'follow_up_consent_at',
        'privacy_notice_version',
        'submission_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_consent_at' => 'datetime',
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
}
