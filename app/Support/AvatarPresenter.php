<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AvatarPresenter
{
    public static function url(?string $path, ?string $seed, string $fallbackSeed = 'friend'): string
    {
        if ($path) {
            return Storage::disk('public')->url($path);
        }

        $safe = rawurlencode(trim($seed ?: $fallbackSeed) ?: 'friend');
        $colors = 'b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf,c1f4c5';

        return "https://api.dicebear.com/9.x/avataaars/svg?seed={$safe}&size=160&backgroundColor={$colors}&radius=50";
    }
}
