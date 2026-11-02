<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-encodes uploaded avatars: strips EXIF/GPS metadata, drops any payload
 * hidden in the original file, and shrinks phone photos to a map-sized WebP.
 */
class AvatarImage
{
    public const MAX_EDGE = 320;

    public static function store(UploadedFile $file): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            return null;
        }

        $source = self::applyExifOrientation($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, 82);
        $bytes = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        $path = 'avatars/'.now()->format('Y/m').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private static function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
