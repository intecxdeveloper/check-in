<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Support\GiftMasterKey;
use App\Support\GiftWheel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftSpinController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'claim_code' => ['required', 'string', 'max:20'],
        ]);

        if (GiftMasterKey::matches($data['claim_code'])) {
            return response()->json([
                'already_spun' => false,
                'master_spin' => true,
                'prize' => GiftWheel::pick(),
                'first_name' => null,
                'claim_code' => null,
            ]);
        }

        $visitor = Visitor::findByClaimCode($data['claim_code']);
        if ($visitor === null) {
            return response()->json([
                'message' => 'That claim code is not on the desk. Check the receipt and try again.',
            ], 404);
        }

        $result = DB::transaction(function () use ($visitor) {
            $locked = Visitor::query()->whereKey($visitor->id)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }

            if (filled($locked->gift_prize)) {
                return [
                    'already_spun' => true,
                    'prize' => $this->prizePayload((string) $locked->gift_prize),
                    'first_name' => $locked->firstName(),
                    'claim_code' => $locked->claimCode(),
                ];
            }

            $prize = GiftWheel::pick();
            $locked->update([
                'gift_prize' => $prize['key'],
                'gift_spun_at' => now(),
            ]);

            return [
                'already_spun' => false,
                'prize' => $prize,
                'first_name' => $locked->firstName(),
                'claim_code' => $locked->claimCode(),
            ];
        });

        if ($result === null) {
            return response()->json([
                'message' => 'That claim code is not on the desk. Check the receipt and try again.',
            ], 404);
        }

        return response()->json($result);
    }

    /**
     * @return array{index: int, key: string, label: string, short: string, color: string}
     */
    private function prizePayload(string $key): array
    {
        return GiftWheel::find($key) ?? [
            'index' => 0,
            'key' => $key,
            'label' => Str::headline($key),
            'short' => Str::headline($key),
            'color' => '#00254e',
        ];
    }
}
