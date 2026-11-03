<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function labelFor(?string $otherInterest): string
    {
        if ($this->slug === 'other' && filled($otherInterest)) {
            return $otherInterest;
        }

        return $this->name;
    }
}
