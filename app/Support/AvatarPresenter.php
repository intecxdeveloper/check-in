<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AvatarPresenter
{
    /**
     * Soft pastel booth backgrounds with a corporate dress code.
     */
    public static function url(?string $path, ?string $seed, string $fallbackSeed = 'friend'): string
    {
        if ($path) {
            return Storage::disk('public')->url($path);
        }

        $safe = trim($seed ?: $fallbackSeed) ?: 'friend';

        $query = http_build_query([
            'seed' => $safe,
            'size' => 160,
            'backgroundColor' => 'b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf,c1f4c5',
            'radius' => 50,
            'clothing' => 'blazerAndShirt,blazerAndSweater,collarAndSweater',
            'clothingColor' => '262e33,3c4f5c,5199e4,25557c,65c9ff,929598',
            'accessories' => 'prescription01,prescription02,round,wayfarers',
            'accessoriesProbability' => 28,
            'facialHairProbability' => 12,
            'mouth' => 'smile,default,twinkle',
            'eyebrows' => 'default,defaultNatural,flatNatural',
        ]);

        return 'https://api.dicebear.com/9.x/avataaars/svg?'.$query;
    }
}
