<?php

namespace App\Support;

use Illuminate\Support\Uri;

class PublicUrl
{
    public static function root(): string
    {
        return rtrim((string) config('expo.public_url'), '/');
    }

    public static function to(string $path): string
    {
        return (string) Uri::of(self::root())->withPath('/'.ltrim($path, '/'));
    }

    public static function hostIsLoopback(?string $url = null): bool
    {
        $host = parse_url($url ?? self::root(), PHP_URL_HOST);

        return is_string($host) && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
