<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->filter(fn (array $row) => str_contains(Str::lower($row['name']), $needle))
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

        foreach ($this->openCorporates($validated['q']) as $row) {
            $key = Str::lower($row['name']);
            if ($results->contains(fn ($item) => Str::lower($item['name']) === $key)) {
                continue;
            }
            $results->push($row);
        }

        return response()->json([
            'results' => $results->take(12)->values(),
            'can_custom' => true,
            'hint' => 'Not listed? Keep typing your company name — that works too.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openCorporates(string $query): array
    {
        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get('https://api.opencorporates.com/v0.4/companies/search', [
                    'q' => $query,
                    'jurisdiction_code' => 'my',
                    'per_page' => 5,
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
