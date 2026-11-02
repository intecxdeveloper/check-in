<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Place search for the check-in map.
 *
 * Primary: Nominatim (OSM). Its usage policy rejects (403) clients whose
 * User-Agent carries a placeholder contact such as "example.com", so the
 * agent string is sanitised and the app URL is sent as Referer.
 * Fallback: Photon (komoot, also OSM data) when Nominatim is blocked,
 * rate-limited or down. Results are cached to respect both services.
 */
class GeocodeController extends Controller
{
    /** Malaysia bounding box: west, south, east, north. */
    private const MY_BBOX = [99.6, 0.85, 119.3, 7.4];

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);
        $q = trim($validated['q']);
        $key = 'geocode:search:v2:'.md5(Str::lower($q));

        if (($cached = Cache::get($key)) !== null) {
            return response()->json(['results' => $cached]);
        }

        $results = $this->nominatimSearch($q) ?? $this->photonSearch($q);

        if ($results === null) {
            return response()->json([
                'message' => 'Place search is unavailable right now. Drag the pin or tap the map instead.',
                'results' => [],
            ], 503);
        }

        Cache::put($key, $results, now()->addDay());

        return response()->json(['results' => $results]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $lat = round((float) $validated['lat'], 5);
        $lng = round((float) $validated['lng'], 5);
        $key = "geocode:reverse:v2:{$lat},{$lng}";

        if (Cache::has($key)) {
            return response()->json(['result' => Cache::get($key)]);
        }

        $result = $this->nominatimReverse($lat, $lng);
        if ($result === false) {
            $result = $this->photonReverse($lat, $lng);
        }
        if ($result === false) {
            return response()->json([
                'message' => 'Could not look up that spot. Pin is still saved.',
                'result' => null,
            ], 503);
        }

        Cache::put($key, $result, now()->addDay());

        return response()->json(['result' => $result]);
    }

    public static function userAgent(): string
    {
        $agent = (string) config('expo.geocoder_user_agent');
        // Placeholder contacts are rejected by Nominatim; drop them.
        $agent = preg_replace('/;?\s*contact:\s*[^);]*example\.(com|org|net)[^);]*/i', '', $agent);
        $agent = trim(preg_replace('/\(\s*\)/', '', $agent));

        return $agent !== '' ? $agent : 'ExpoVisitorMap/1.0';
    }

    private function client(): PendingRequest
    {
        return Http::timeout(6)->connectTimeout(4)->withHeaders([
            'User-Agent' => self::userAgent(),
            'Referer' => rtrim((string) config('app.url'), '/').'/',
            'Accept' => 'application/json',
            'Accept-Language' => 'en,ms',
        ]);
    }

    private function nominatimSearch(string $q): ?array
    {
        [$w, $s, $e, $n] = self::MY_BBOX;
        $response = $this->get('https://nominatim.openstreetmap.org/search', [
            'q' => $q,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 6,
            'countrycodes' => 'my',
            'viewbox' => "$w,$n,$e,$s",
        ]);
        if (! $response || ! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        return collect($response->json())
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->formatNominatim($item))
            ->values()
            ->all();
    }

    private function photonSearch(string $q): ?array
    {
        $response = $this->get('https://photon.komoot.io/api/', [
            'q' => $q,
            'limit' => 6,
            'lat' => 3.139,
            'lon' => 101.6869,
            'bbox' => implode(',', self::MY_BBOX),
        ]);
        if (! $response || ! $response->successful()) {
            return null;
        }

        return collect($response->json('features') ?? [])
            ->filter(fn ($f) => is_array($f) && ($f['properties']['countrycode'] ?? 'MY') === 'MY')
            ->map(fn (array $f) => $this->formatPhoton($f))
            ->values()
            ->all();
    }

    /** @return array|null|false false = service failure */
    private function nominatimReverse(float $lat, float $lng): array|null|false
    {
        $response = $this->get('https://nominatim.openstreetmap.org/reverse', [
            'lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'addressdetails' => 1,
        ]);
        if (! $response || ! $response->successful()) {
            return false;
        }
        $payload = $response->json();

        return is_array($payload) && isset($payload['lat']) ? $this->formatNominatim($payload) : null;
    }

    private function photonReverse(float $lat, float $lng): array|null|false
    {
        $response = $this->get('https://photon.komoot.io/reverse', ['lat' => $lat, 'lon' => $lng, 'limit' => 1]);
        if (! $response || ! $response->successful()) {
            return false;
        }
        $feature = $response->json('features.0');

        return is_array($feature) ? $this->formatPhoton($feature) : null;
    }

    private function get(string $url, array $query): ?Response
    {
        try {
            return $this->client()->get($url, $query);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function formatNominatim(array $item): array
    {
        $address = $item['address'] ?? [];
        $label = $item['display_name'] ?? '';

        return [
            'label' => $label,
            'name' => $item['name'] ?? Str::before($label, ','),
            'latitude' => (float) ($item['lat'] ?? 0),
            'longitude' => (float) ($item['lon'] ?? 0),
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? $address['suburb'] ?? null,
            'region' => $address['state'] ?? $address['region'] ?? null,
            'country' => $address['country'] ?? null,
            'address_text' => $label ?: null,
        ];
    }

    private function formatPhoton(array $feature): array
    {
        $p = $feature['properties'] ?? [];
        [$lng, $lat] = $feature['geometry']['coordinates'] ?? [0, 0];
        $street = trim(($p['housenumber'] ?? '').' '.($p['street'] ?? ''));
        $parts = array_values(array_unique(array_filter([
            $p['name'] ?? null, $street ?: null, $p['district'] ?? null, $p['city'] ?? null,
            $p['postcode'] ?? null, $p['state'] ?? null, $p['country'] ?? null,
        ])));
        $label = implode(', ', $parts);

        return [
            'label' => $label,
            'name' => $p['name'] ?? ($parts[0] ?? ''),
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'city' => $p['city'] ?? $p['district'] ?? $p['county'] ?? null,
            'region' => $p['state'] ?? null,
            'country' => $p['country'] ?? null,
            'address_text' => $label ?: null,
        ];
    }
}
