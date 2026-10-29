<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Service;
use App\Models\Visitor;
use App\Support\AvatarPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request): View
    {
        return view('map.index', [
            'services' => $this->interestServices(),
            'privacyVersion' => config('expo.privacy_notice_version'),
            'checkInUrl' => $request->getSchemeAndHttpHost().route('check-in', absolute: false),
        ]);
    }

    public function checkIn(): View
    {
        return view('map.checkin', [
            'services' => $this->interestServices(),
            'privacyVersion' => config('expo.privacy_notice_version'),
        ]);
    }

    /**
     * @return Collection<int, Service>
     */
    private function interestServices()
    {
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

        return Service::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']);
    }

    public function companies(Request $request): JsonResponse
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

        $companies = $query->latest('updated_at')->get();
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

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $publicCompanies = Company::query()
            ->where('public_display_enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $regions = (clone $publicCompanies)
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(region, ''), NULLIF(country, ''), NULLIF(city, ''))) as aggregate")
            ->value('aggregate');

        return response()->json([
            'check_ins' => Visitor::count(),
            'companies' => (clone $publicCompanies)->count(),
            'regions' => (int) $regions,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function recent(): JsonResponse
    {
        $items = Visitor::query()
            ->with(['company:id,name,city,region,country,public_display_enabled,latitude,longitude'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (Visitor $visitor) {
                $company = $visitor->company;

                $firstName = trim(explode(' ', trim((string) $visitor->full_name))[0] ?? '');

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
                    'public' => (bool) $company?->public_display_enabled,
                    'latitude' => $company?->latitude,
                    'longitude' => $company?->longitude,
                    'at' => $visitor->created_at?->diffForHumans(),
                    'created_at' => $visitor->created_at?->toIso8601String(),
                ];
            });

        return response()->json(['items' => $items]);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'services' => $this->interestServices()->values(),
        ]);
    }
}
