<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Gift extends Model
{
    protected $fillable = [
        'key',
        'label',
        'short',
        'color',
        'weight',
        'sort_order',
    ];

    /**
     * @var list<string>
     */
    public const COLORS = [
        '#00254e',
        '#c8141e',
        '#0058b8',
        '#0a3566',
        '#0078e0',
    ];

    /**
     * @return array{key: string, label: string, short: string, color: string, weight: int}
     */
    public function toWheelPrize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'short' => $this->short,
            'color' => $this->color,
            'weight' => (int) $this->weight,
        ];
    }

    public static function nextSortOrder(): int
    {
        return (int) self::query()->max('sort_order') + 1;
    }

    public static function uniqueKeyFromLabel(string $label): string
    {
        $base = Str::slug($label) ?: 'gift';
        $key = Str::limit($base, 32, '');
        $suffix = 1;

        while (self::query()->where('key', $key)->exists()) {
            $suffix++;
            $key = Str::limit($base, 28, '').'-'.$suffix;
        }

        return $key;
    }

    public static function nextColor(): string
    {
        $index = self::query()->count() % count(self::COLORS);

        return self::COLORS[$index];
    }

    public static function loadStarterPrizes(): int
    {
        $added = 0;

        foreach (config('expo.gift_prizes', []) as $index => $prize) {
            $gift = self::query()->firstOrCreate(
                ['key' => $prize['key']],
                [
                    'label' => $prize['label'],
                    'short' => $prize['short'],
                    'color' => $prize['color'],
                    'weight' => $prize['weight'] ?? 1,
                    'sort_order' => $index + 1,
                ]
            );

            if ($gift->wasRecentlyCreated) {
                $added++;
            }
        }

        return $added;
    }
}
