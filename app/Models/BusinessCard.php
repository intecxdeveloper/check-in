<?php

namespace App\Models;

use Database\Factories\BusinessCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessCard extends Model
{
    /** @use HasFactory<BusinessCardFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scan_session_id',
        'image_path',
        'full_name',
        'job_title',
        'company_name',
        'email',
        'phone',
        'website',
        'address',
        'ocr_text',
        'notes',
    ];

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
        if (! filled($this->image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function displayName(): string
    {
        return filled($this->full_name) ? (string) $this->full_name : 'Unnamed card';
    }

    /**
     * @return array{
     *     id: int,
     *     image_url: ?string,
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
