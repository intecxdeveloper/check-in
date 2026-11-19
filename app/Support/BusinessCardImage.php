<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class BusinessCardImage
{
    public const MAX_EDGE = 1600;

    /**
     * @return array{base64: string, mime: string}|null
     */
    public static function fromUpload(UploadedFile $file): ?array
    {
        $bytes = (string) file_get_contents($file->getRealPath());

        return self::encode($bytes, $file->getMimeType());
    }

    /**
     * @return array{base64: string, mime: string}|null
     */
    public static function fromDataUri(string $uri): ?array
    {
        if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#i', trim($uri), $matches) !== 1) {
            return null;
        }

        $bytes = base64_decode($matches[2], true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return self::encode($bytes, 'image/'.$matches[1]);
    }

    /**
     * @return array{base64: string, mime: string}|null
     */
    public static function encode(string $bytes, ?string $mime = null): ?array
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $source = self::applyExifOrientation($source, $bytes, $mime);

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
        imagewebp($canvas, null, 84);
        $encoded = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if ($encoded === '') {
            return null;
        }

        return [
            'base64' => base64_encode($encoded),
            'mime' => 'image/webp',
        ];
    }

    private static function applyExifOrientation(\GdImage $image, string $bytes, ?string $mime): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $temp = tmpfile();
        if ($temp === false) {
            return $image;
        }

        fwrite($temp, $bytes);
        $path = stream_get_meta_data($temp)['uri'] ?? null;
        $orientation = is_string($path)
            ? (int) (@exif_read_data($path)['Orientation'] ?? 1)
            : 1;
        fclose($temp);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
