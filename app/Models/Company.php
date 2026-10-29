<?php

namespace App\Models;

use App\Support\AvatarPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'industry',
        'address_text',
        'city',
        'region',
        'country',
        'latitude',
        'longitude',
        'public_display_enabled',
        'public_website',
        'avatar_seed',
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'public_display_enabled' => 'boolean',
        ];
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function locationLabel(): string
    {
        return collect([$this->city, $this->region, $this->country])
            ->filter()
            ->implode(', ');
    }

    public function toPublicMapFeature(): array
    {
        // MapLibre clustering requires primitive GeoJSON properties only.
        $services = $this->services->map(fn (Service $s) => [
            'name' => $s->name,
            'slug' => $s->slug,
            'color' => $s->color,
        ])->values()->all();

        $avatarPath = $this->map_avatar_path ?? $this->avatar_path;
        $avatarSeed = $this->map_avatar_seed ?? $this->avatar_seed ?? $this->name;

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $this->longitude, (float) $this->latitude],
            ],
            'properties' => [
                'id' => (int) $this->id,
                'name' => (string) $this->name,
                'city' => (string) ($this->city ?? ''),
                'region' => (string) ($this->region ?? ''),
                'country' => (string) ($this->country ?? ''),
                'location' => (string) $this->locationLabel(),
                'website' => (string) ($this->public_website ?? ''),
                'message' => (string) ($this->latest_public_message ?? ''),
                'avatar_url' => AvatarPresenter::url($avatarPath, $avatarSeed, (string) $this->name),
                'visitor_count' => (int) ($this->visitors_count ?? $this->visitors()->count()),
                'services' => json_encode($services),
                'service_slugs' => $this->services->pluck('slug')->implode(','),
                'created_at' => (string) optional($this->created_at)?->toIso8601String(),
            ],
        ];
    }
}
