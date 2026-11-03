<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MapCache
{
    /**
     * Versioned key for public map payloads so a check-in or moderation
     * immediately hides stale pins, stats, and messages.
     */
    public static function publicKey(string $name): string
    {
        return 'map:v'.self::version().':'.$name;
    }

    public static function version(): int
    {
        return (int) Cache::get('map:cache_version', 1);
    }

    public static function flushPublic(): void
    {
        Cache::forever('map:cache_version', self::version() + 1);
    }
}
