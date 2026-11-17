<?php

namespace App\Support;

use App\Models\Gift;

class GiftWheel
{
    /**
     * @return list<array{key: string, label: string, short: string, color: string, weight: int}>
     */
    public static function prizes(): array
    {
        $gifts = Gift::query()->orderBy('sort_order')->orderBy('id')->get();
        if ($gifts->isNotEmpty()) {
            return $gifts->map->toWheelPrize()->values()->all();
        }

        /** @var list<array{key: string, label: string, short: string, color: string, weight: int}> $prizes */
        $prizes = config('expo.gift_prizes', []);

        return array_values($prizes);
    }

    /**
     * @return array{index: int, key: string, label: string, short: string, color: string}
     */
    public static function pick(): array
    {
        $prizes = self::prizes();
        if ($prizes === []) {
            return [
                'index' => 0,
                'key' => 'mystery',
                'label' => 'Mystery gift',
                'short' => 'Mystery',
                'color' => '#00254e',
            ];
        }

        $total = max(1, (int) array_sum(array_column($prizes, 'weight')));
        $ticket = random_int(1, $total);
        $running = 0;

        foreach ($prizes as $index => $prize) {
            $running += max(1, (int) ($prize['weight'] ?? 1));
            if ($ticket <= $running) {
                return self::payload($prize, $index);
            }
        }

        return self::payload($prizes[0], 0);
    }

    /**
     * @return array{index: int, key: string, label: string, short: string, color: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::prizes() as $index => $prize) {
            if (($prize['key'] ?? '') === $key) {
                return self::payload($prize, $index);
            }
        }

        return null;
    }

    /**
     * @param  array{key?: string, label?: string, short?: string, color?: string}  $prize
     * @return array{index: int, key: string, label: string, short: string, color: string}
     */
    private static function payload(array $prize, int $index): array
    {
        $label = (string) ($prize['label'] ?? 'Mystery gift');

        return [
            'index' => $index,
            'key' => (string) ($prize['key'] ?? 'mystery'),
            'label' => $label,
            'short' => (string) ($prize['short'] ?? $label),
            'color' => (string) ($prize['color'] ?? '#00254e'),
        ];
    }
}
