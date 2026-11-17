<?php

namespace App\Support;

use App\Models\Setting;

class GiftMasterKey
{
    public const SETTING_KEY = 'gift_master_key';

    public static function current(): string
    {
        $stored = Setting::getValue(self::SETTING_KEY);
        $value = $stored ?? (string) config('expo.gift_master_key', '');

        return self::normalize($value);
    }

    public static function matches(string $code): bool
    {
        $master = self::current();
        $given = self::normalize($code);

        if ($master === '' || $given === '' || strlen($master) !== strlen($given)) {
            return false;
        }

        return hash_equals($master, $given);
    }

    public static function put(string $code): void
    {
        Setting::putValue(self::SETTING_KEY, self::normalize($code));
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $code));
    }
}
