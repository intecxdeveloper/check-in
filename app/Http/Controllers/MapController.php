<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Service;
use App\Models\Visitor;
use App\Support\AvatarPresenter;
use App\Support\MapCache;
use DateInterval;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MapController extends Controller
{
    /** Short shared cache so a full expo hall polling every few seconds hits the DB once. */
    public const PUBLIC_CACHE_SECONDS = 5;

    public const MAX_MAP_COMPANIES = 2000;

    public function index(Request $request): View
    {
        $centroid = $this->cachedArray(MapCache::publicKey('centroid'), now()->addMinutes(2), fn () => $this->mapCentroid());

        return view('map.index', [
            'services' => $this->interestServices(),
            'privacyVersion' => config('expo.privacy_notice_version'),
            'checkInUrl' => $request->getSchemeAndHttpHost().route('check-in', absolute: false),
            'mapCenterLng' => (float) ($centroid['lng'] ?? config('expo.map_center_lng')),
            'mapCenterLat' => (float) ($centroid['lat'] ?? config('expo.map_center_lat')),
            'viteEntry' => 'resources/js/map.js',
            'loadMapAssets' => true,
        ]);
    }

    public function checkIn(): View
    {
        $brand = config('expo.brand_name');

        return view('map.checkin', [
            'services' => $this->interestServices(),
            'privacyVersion' => config('expo.privacy_notice_version'),
            'title' => $brand.' · Check in',
            'ogTitle' => $brand.' · Check in',
            'description' => 'Check in at the '.$brand.' booth in about a minute. Your name stays private.',
            'viteEntry' => 'resources/js/checkin.js',
        ]);
    }

    /**
     * @return Collection<int, array{id:int, name:string, slug:string, color:?string}>
     */
    private function interestServices()
    {
        return once(function () {
            $cached = $this->cachedArray('map:services', now()->addMinutes(10), function () {
                if (Service::query()->doesntExist()) {
                    foreach ([
                        ['name' => 'GIS / Geospatial', 'slug' => 'gis', 'color' => '#38bdf8'],
                        ['name' => 'Land Management', 'slug' => 'land', 'color' => '#34d399'],
                        ['name' => 'Software / IT', 'slug' => 'software', 'color' => '#a78bfa'],
                        ['name' => 'Data Analytics', 'slug' => 'analytics', 'color' => '#fbbf24'],
                        ['name' => 'System Integration', 'slug' => 'integration', 'color' => '#fb7185'],
                        ['name' => 'Other', 'slug' => 'other', 'color' => '#94a3b8'],
                    ] as $service) {
                        Service::query()->create($service);
                    }
                }

                return Service::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'color'])
                    ->map(fn (Service $service) => [
                        'id' => $service->id,
                        'name' => $service->name,
                        'slug' => $service->slug,
                        'color' => $service->color,
                    ])
                    ->values()
                    ->all();
            });

            return collect($cached);
        });
    }

    /**
     * @param  callable(): array<string, mixed>|list<mixed>  $callback
     * @return array<string, mixed>|list<mixed>
     */
    private function cachedArray(string $key, DateTimeInterface|DateInterval|int $ttl, callable $callback): array
    {
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            Cache::forget($key);
            $cached = Cache::remember($key, $ttl, $callback);
        }

        return is_array($cached) ? $cached : [];
    }

    /**
     * @return array{lng: ?float, lat: ?float}
     */
    private function mapCentroid(): array
    {
        $row = Company::query()
            ->where('public_display_enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('AVG(longitude) as lng, AVG(latitude) as lat')
            ->first();

        return [
            'lng' => $row?->lng !== null ? (float) $row->lng : null,
            'lat' => $row?->lat !== null ? (float) $row->lat : null,
        ];
    }

    public function companies(Request $request): JsonResponse
    {
        $request->validate([
            'service' => ['nullable', 'string', 'max:60'],
            'region' => ['nullable', 'string', 'max:120'],
            'since' => ['nullable', 'date'],
        ]);

        $cacheKey = MapCache::publicKey('companies:'.md5(json_encode($request->only(['service', 'region', 'since']))));

        $payload = Cache::flexible($cacheKey, [self::PUBLIC_CACHE_SECONDS, self::PUBLIC_CACHE_SECONDS * 4], fn () => $this->buildCompaniesPayload($request));
        if (! $this->isFeatureCollection($payload)) {
            Cache::forget($cacheKey);
            $payload = $this->buildCompaniesPayload($request);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age='.self::PUBLIC_CACHE_SECONDS);
    }

    private function buildCompaniesPayload(Request $request): array
    {
        $query = Company::query()
            ->with(['services:id,name,slug,color'])
            ->withCount('visitors')
            ->where('public_display_enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($service = $request->string('service')->toString()) {
            $query->whereHas('services', fn ($q) => $q->where('slug', $service));
        }

        if ($region = $request->string('region')->toString()) {
            $query->where(function ($q) use ($region) {
                $q->where('region', $region)
                    ->orWhere('country', $region)
                    ->orWhere('city', $region);
            });
        }

        $since = $request->string('since')->toString();
        if ($since !== '') {
            $query->where('updated_at', '>=', $since);
        }

        $companies = $query
            ->select([
                'id',
                'name',
                'city',
                'region',
                'country',
                'latitude',
                'longitude',
                'public_website',
                'other_interest',
                'avatar_path',
                'avatar_seed',
                'created_at',
                'updated_at',
            ])
            ->latest('updated_at')
            ->limit(self::MAX_MAP_COMPANIES)
            ->get();
        $companyIds = $companies->pluck('id');

        $latestMessages = Visitor::query()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('display_message')
            ->where('display_message', '!=', '')
            ->latest('id')
            ->get(['company_id', 'display_message'])
            ->unique('company_id')
            ->keyBy('company_id');

        $latestAvatars = Visitor::query()
            ->whereIn('company_id', $companyIds)
            ->where(function ($q) {
                $q->whereNotNull('avatar_path')->orWhereNotNull('avatar_seed');
            })
            ->latest('id')
            ->get(['company_id', 'avatar_path', 'avatar_seed'])
            ->unique('company_id')
            ->keyBy('company_id');

        $features = $companies->map(function (Company $company) use ($latestMessages, $latestAvatars) {
            $company->latest_public_message = $latestMessages->get($company->id)?->display_message ?? '';
            $avatar = $latestAvatars->get($company->id);
            $company->map_avatar_path = $avatar?->avatar_path ?: $company->avatar_path;
            $company->map_avatar_seed = $avatar?->avatar_seed ?: $company->avatar_seed ?: $company->name;

            return $company->toPublicMapFeature();
        })->values();

        return [
            'type' => 'FeatureCollection',
            'features' => $features->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function stats(): JsonResponse
    {
        $cacheKey = MapCache::publicKey('stats');
        $payload = Cache::flexible($cacheKey, [self::PUBLIC_CACHE_SECONDS, self::PUBLIC_CACHE_SECONDS * 4], fn () => $this->buildStatsPayload());
        if (! is_array($payload) || ! array_key_exists('check_ins', $payload)) {
            Cache::forget($cacheKey);
            $payload = $this->buildStatsPayload();
        }

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age='.self::PUBLIC_CACHE_SECONDS);
    }

    private function buildStatsPayload(): array
    {
        $publicCompanies = Company::query()
            ->where('public_display_enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $regions = (clone $publicCompanies)
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(region, ''), NULLIF(country, ''), NULLIF(city, ''))) as aggregate")
            ->value('aggregate');

        return [
            'check_ins' => Visitor::count(),
            'companies' => (clone $publicCompanies)->count(),
            'regions' => (int) $regions,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function recent(): JsonResponse
    {
        $cacheKey = MapCache::publicKey('recent');
        $payload = Cache::flexible($cacheKey, [self::PUBLIC_CACHE_SECONDS, self::PUBLIC_CACHE_SECONDS * 4], fn () => [
            'items' => $this->buildRecentItems()->values()->all(),
        ]);
        if (! is_array($payload) || ! is_array($payload['items'] ?? null)) {
            Cache::forget($cacheKey);
            $payload = [
                'items' => $this->buildRecentItems()->values()->all(),
            ];
        }

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age='.self::PUBLIC_CACHE_SECONDS);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRecentItems(): Collection
    {
        return Visitor::query()
            ->select(['id', 'company_id', 'full_name', 'display_message', 'avatar_path', 'avatar_seed', 'created_at'])
            ->with(['company:id,name,city,region,country,public_display_enabled,latitude,longitude'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (Visitor $visitor) {
                $company = $visitor->company;
                $isPublic = (bool) $company?->public_display_enabled;

                $firstName = trim(explode(' ', trim((string) $visitor->full_name))[0] ?? '');

                // Visitors who opted out of the public map stay anonymous on the
                // public feed: no company, location, message or photo.
                if (! $isPublic) {
                    return [
                        'id' => $visitor->id,
                        'company_id' => null,
                        'first_name' => null,
                        'company' => null,
                        'location' => null,
                        'message' => null,
                        'avatar_url' => AvatarPresenter::url(null, null, 'guest-'.$visitor->id),
                        'public' => false,
                        'latitude' => null,
                        'longitude' => null,
                        'at' => $visitor->created_at?->diffForHumans(),
                        'created_at' => $visitor->created_at?->toIso8601String(),
                    ];
                }

                return [
                    'id' => $visitor->id,
                    'company_id' => $company?->id,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'company' => $company?->name,
                    'location' => $company?->locationLabel(),
                    'message' => $visitor->display_message,
                    'avatar_url' => AvatarPresenter::url(
                        $visitor->avatar_path,
                        $visitor->avatar_seed,
                        $company?->name ?: ($firstName ?: 'friend')
                    ),
                    'public' => true,
                    'latitude' => $company?->latitude,
                    'longitude' => $company?->longitude,
                    'at' => $visitor->created_at?->diffForHumans(),
                    'created_at' => $visitor->created_at?->toIso8601String(),
                ];
            });
    }

    /**
     * @param  mixed  $payload
     */
    private function isFeatureCollection($payload): bool
    {
        return is_array($payload)
            && ($payload['type'] ?? null) === 'FeatureCollection'
            && is_array($payload['features'] ?? null)
            && array_is_list($payload['features']);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'services' => $this->interestServices()->values(),
        ]);
    }
}
