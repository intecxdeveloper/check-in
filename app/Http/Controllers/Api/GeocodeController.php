<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('expo.geocoder_user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $validated['q'],
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 6,
                    'countrycodes' => 'my',
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Location search is temporarily unavailable. Drop a pin on the map instead.',
                'results' => [],
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Location search failed. You can place a pin manually.',
                'results' => [],
            ], 502);
        }

        return response()->json([
            'results' => collect($response->json())
                ->map(fn (array $item) => $this->formatResult($item))
                ->values(),
        ]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('expo.geocoder_user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $validated['lat'],
                    'lon' => $validated['lng'],
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Could not look up that spot. Pin is still saved.',
                'result' => null,
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Could not look up that spot. Pin is still saved.',
                'result' => null,
            ], 502);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return response()->json(['result' => null]);
        }

        return response()->json([
            'result' => $this->formatResult($payload),
        ]);
    }

    private function formatResult(array $item): array
    {
        $address = $item['address'] ?? [];

        return [
            'label' => $item['display_name'] ?? '',
            'latitude' => (float) ($item['lat'] ?? 0),
            'longitude' => (float) ($item['lon'] ?? 0),
            'city' => $address['city']
                ?? $address['town']
                ?? $address['village']
                ?? $address['municipality']
                ?? null,
            'region' => $address['state'] ?? $address['region'] ?? null,
            'country' => $address['country'] ?? null,
            'address_text' => $item['display_name'] ?? null,
        ];
    }
}
