<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CompanySearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        $needle = Str::lower(trim($validated['q']));
        $results = collect();

        Company::query()
            ->where('name', 'like', '%'.$validated['q'].'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'city', 'region', 'country', 'latitude', 'longitude', 'public_website'])
            ->each(function (Company $company) use ($results) {
                $results->push([
                    'source' => 'expo',
                    'name' => $company->name,
                    'city' => $company->city,
                    'region' => $company->region,
                    'country' => $company->country ?: 'Malaysia',
                    'latitude' => $company->latitude,
                    'longitude' => $company->longitude,
                    'location' => $company->locationLabel(),
                    'has_location' => $company->latitude !== null && $company->longitude !== null,
                    'website' => $company->public_website,
                ]);
            });

        collect(config('malaysia_companies', []))
            ->filter(fn (array $row) => $this->matchesNeedle(Str::lower($row['name']), $needle))
            ->take(10)
            ->each(function (array $row) use ($results) {
                $key = Str::lower($row['name']);
                if ($results->contains(fn ($item) => Str::lower($item['name']) === $key)) {
                    return;
                }

                $results->push([
                    'source' => 'directory',
                    'name' => $row['name'],
                    'city' => $row['city'] ?? null,
                    'region' => $row['region'] ?? null,
                    'country' => 'Malaysia',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                    'location' => collect([$row['city'] ?? null, $row['region'] ?? null, 'Malaysia'])->filter()->implode(', '),
                    'has_location' => isset($row['latitude'], $row['longitude']),
                    'website' => null,
                ]);
            });

        // Remote registry only when a token is configured and local search found nothing.
        if ($this->shouldQueryOpenCorporates($results, $needle)) {
            foreach ($this->openCorporatesCached($validated['q']) as $row) {
                $key = Str::lower($row['name']);
                if ($results->contains(fn ($item) => Str::lower($item['name']) === $key)) {
                    continue;
                }
                $results->push($row);
            }
        }

        return response()->json([
            'results' => $results->take(12)->values(),
            'can_custom' => true,
            'hint' => 'Not listed? Keep typing your company name — that works too.',
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $results
     */
    private function shouldQueryOpenCorporates($results, string $needle): bool
    {
        if (! config('expo.company_search_opencorporates', true)) {
            return false;
        }

        if (! filled(config('expo.opencorporates_api_token'))) {
            return false;
        }

        return $results->isEmpty() && Str::length($needle) >= 2;
    }

    private function matchesNeedle(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        if (str_contains($haystack, $needle)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $needle) ?: [];

        return collect($tokens)
            ->filter(fn (string $token) => Str::length($token) >= 2)
            ->isNotEmpty()
            && collect($tokens)
                ->filter(fn (string $token) => Str::length($token) >= 2)
                ->every(fn (string $token) => str_contains($haystack, $token));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openCorporatesCached(string $query): array
    {
        $key = 'company-search:oc:my:'.md5(Str::lower(trim($query)));

        /** @var array<int, array<string, mixed>> $rows */
        $rows = Cache::remember($key, now()->addHours(6), function () use ($query) {
            return $this->openCorporates($query);
        });

        if ($rows === []) {
            Cache::put($key, [], now()->addMinutes(2));
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openCorporates(string $query): array
    {
        $token = config('expo.opencorporates_api_token');

        try {
            $response = Http::connectTimeout(1)
                ->timeout(2)
                ->acceptJson()
                ->get('https://api.opencorporates.com/v0.4/companies/search', [
                    'q' => $query,
                    'jurisdiction_code' => 'my',
                    'per_page' => 8,
                    'api_token' => $token,
                ]);
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $companies = data_get($response->json(), 'results.companies', []);

        return collect($companies)
            ->map(function (array $wrap) {
                $company = $wrap['company'] ?? [];
                $name = trim((string) ($company['name'] ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'source' => 'opencorporates',
                    'name' => Str::title(Str::lower($name)),
                    'city' => null,
                    'region' => null,
                    'country' => 'Malaysia',
                    'latitude' => null,
                    'longitude' => null,
                    'location' => 'Malaysia · pick a spot on the map',
                    'has_location' => false,
                    'website' => null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
