<?php

namespace App\Models;

use Database\Factories\BusinessCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCard extends Model
{
    /** @use HasFactory<BusinessCardFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scan_session_id',
        'image_path',
        'image_base64',
        'image_mime',
        'full_name',
        'job_title',
        'company_name',
        'email',
        'phone',
        'website',
        'address',
        'ocr_text',
        'ocr_completed_at',
        'notes',
    ];

    protected $hidden = [
        'image_base64',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocr_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanSession(): BelongsTo
    {
        return $this->belongsTo(BusinessCardScanSession::class, 'scan_session_id');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_base64) && ! filled($this->image_path)) {
            return null;
        }

        return route('staff.cards.image', $this);
    }

    public function imageBinary(): ?string
    {
        if (! filled($this->image_base64)) {
            return null;
        }

        $bytes = base64_decode((string) $this->image_base64, true);

        return $bytes === false ? null : $bytes;
    }

    public function needsOcr(): bool
    {
        return $this->ocr_completed_at === null && filled($this->image_base64);
    }

    public function displayName(): string
    {
        return filled($this->full_name) ? (string) $this->full_name : 'Unnamed card';
    }

    /**
     * @return array{
     *     id: int,
     *     image_url: ?string,
     *     ocr_url: ?string,
     *     full_name: ?string,
     *     job_title: ?string,
     *     company_name: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     website: ?string,
     *     address: ?string,
     *     notes: ?string,
     *     ocr_text: ?string,
     *     created_at: ?string
     * }
     */
    public function toGalleryArray(): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->imageUrl(),
            'ocr_url' => $this->needsOcr() ? route('staff.cards.ocr', $this) : null,
            'full_name' => $this->full_name,
            'job_title' => $this->job_title,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'notes' => $this->notes,
            'ocr_text' => $this->ocr_text,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
