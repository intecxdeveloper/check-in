<?php

namespace App\Support;

use Illuminate\Support\Str;

class BusinessCardParser
{
    /**
     * @return array{
     *     full_name: ?string,
     *     job_title: ?string,
     *     company_name: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     website: ?string,
     *     address: ?string
     * }
     */
    public static function parse(string $text): array
    {
        $result = [
            'full_name' => null,
            'job_title' => null,
            'company_name' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => null,
        ];

        $text = trim($text);

        if ($text === '') {
            return $result;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $match) === 1) {
            $result['email'] = Str::lower($match[0]);
        }

        if (preg_match('/(?:https?:\/\/|www\.)[^\s<>]+/i', $text, $match) === 1) {
            $url = rtrim($match[0], '.,);');
            $result['website'] = str_starts_with(Str::lower($url), 'http') ? $url : 'https://'.$url;
        }

        if (preg_match('/(?:\+?\s*6\s*0|0)\s*1\d(?:[\s\-]?\d){7,8}|\+\d{1,3}[\s\-]?(?:\d[\s\-]?){7,14}\d/', $text, $match) === 1) {
            $digits = preg_replace('/[^\d+]/', '', $match[0]) ?? '';
            $result['phone'] = $digits !== '' ? $digits : null;
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $lines = array_values(array_filter(array_map(trim(...), $lines), fn (string $line): bool => $line !== ''));

        foreach ($lines as $line) {
            if ($result['company_name'] === null && preg_match('/\b(sdn\.?\s*bhd\.?|berhad|\bbhd\.?|ltd\.?|limited|inc\.?|llc|gmbh|pte\.?\s*ltd)\b/i', $line) === 1) {
                $result['company_name'] = self::cleanLine($line);
            }

            if ($result['job_title'] === null && preg_match('/\b(ceo|cto|cfo|coo|director|manager|engineer|officer|consultant|founder|president|head|lead|specialist|executive|coordinator|architect|analyst|designer)\b/i', $line) === 1) {
                $result['job_title'] = self::cleanLine($line);
            }

            if ($result['address'] === null && preg_match('/\d+.+\b(jalan|jln|road|street|st\.|avenue|ave|city|selangor|kuala|malaysia|pulau|sabah|sarawak)\b/i', $line) === 1) {
                $result['address'] = self::cleanLine($line);
            }
        }

        foreach ($lines as $line) {
            if (self::looksLikeName($line, $result)) {
                $result['full_name'] = self::cleanLine($line);
                break;
            }
        }

        return $result;
    }

    /**
     * Keep values the phone already sent; fill only empty fields from OCR text.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function fillBlanks(array $fields, string $ocrText): array
    {
        foreach (self::parse($ocrText) as $key => $value) {
            if (! filled($fields[$key] ?? null) && filled($value)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array{email: ?string, website: ?string, company_name: ?string, job_title: ?string}  $taken
     */
    private static function looksLikeName(string $line, array $taken): bool
    {
        $clean = self::cleanLine($line);

        if (in_array($clean, [$taken['email'], $taken['website'], $taken['company_name'], $taken['job_title']], true)) {
            return false;
        }

        if (str_contains($clean, '@') || preg_match('/\d/', $clean) === 1) {
            return false;
        }

        if (preg_match('/\b(tel|fax|phone|mobile|email|www|http)\b/i', $clean) === 1) {
            return false;
        }

        $words = preg_split('/\s+/', $clean) ?: [];

        return count($words) >= 2 && count($words) <= 5 && mb_strlen($clean) <= 60;
    }

    private static function cleanLine(string $line): string
    {
        return trim(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
