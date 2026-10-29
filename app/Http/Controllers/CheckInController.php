<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckInRequest;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Visitor;
use App\Support\AvatarPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckInController extends Controller
{
    public function store(StoreCheckInRequest $request): JsonResponse
    {
        $data = $request->validated();

        $fingerprint = hash('sha256', implode('|', [
            Str::lower(trim($data['email'] ?? '')),
            preg_replace('/\D+/', '', $data['phone'] ?? ''),
            Str::lower(trim($data['company_name'])),
            now()->toDateString(),
        ]));

        $duplicate = Visitor::query()
            ->where('submission_fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Looks like you already checked in recently. Thanks!',
                'duplicate' => true,
            ], 429);
        }

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars/'.now()->format('Y/m'), 'public');
        }

        $avatarSeed = filled($data['avatar_seed'] ?? null)
            ? trim((string) $data['avatar_seed'])
            : trim((string) $data['full_name']);

        $result = DB::transaction(function () use ($data, $fingerprint, $avatarPath, $avatarSeed) {
            $company = $this->resolveCompany($data, $avatarPath, $avatarSeed);

            $company->services()->sync($data['services']);

            $message = isset($data['display_message'])
                ? trim((string) $data['display_message'])
                : null;

            $visitor = Visitor::create([
                'full_name' => $data['full_name'],
                'job_title' => $data['job_title'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'display_message' => $message !== '' ? $message : null,
                'avatar_seed' => $avatarPath ? null : $avatarSeed,
                'avatar_path' => $avatarPath,
                'company_id' => $company->id,
                'follow_up_consent_at' => ($data['follow_up_consent'] ?? false) ? now() : null,
                'privacy_notice_version' => config('expo.privacy_notice_version'),
                'submission_fingerprint' => $fingerprint,
            ]);

            Lead::create([
                'visitor_id' => $visitor->id,
                'status' => 'new',
            ]);

            $company->load(['services:id,name,slug,color'])->loadCount('visitors');

            $company->latest_public_message = $visitor->display_message ?? '';
            $company->map_avatar_path = $visitor->avatar_path ?: $company->avatar_path;
            $company->map_avatar_seed = $visitor->avatar_seed ?: $company->avatar_seed ?: $company->name;

            $firstName = trim(explode(' ', trim((string) $visitor->full_name))[0] ?? '');
            $mapFeature = $company->public_display_enabled
                ? $company->toPublicMapFeature()
                : null;

            if ($mapFeature && $visitor->display_message) {
                $mapFeature['properties']['message'] = $visitor->display_message;
            }

            return [
                'visitor_id' => $visitor->id,
                'first_name' => $firstName !== '' ? $firstName : null,
                'display_message' => $visitor->display_message,
                'avatar_url' => AvatarPresenter::url($visitor->avatar_path, $visitor->avatar_seed, $company->name),
                'company' => $mapFeature,
                'public_display_enabled' => $company->public_display_enabled,
            ];
        });

        return response()->json([
            'message' => 'Welcome to the network!',
            'duplicate' => false,
            ...$result,
        ], 201);
    }

    private function resolveCompany(array $data, ?string $avatarPath, string $avatarSeed): Company
    {
        $normalized = Str::lower(trim($data['company_name']));

        $existing = Company::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->when(
                ! empty($data['city']),
                fn ($q) => $q->whereRaw('LOWER(COALESCE(city, ?)) = ?', ['', Str::lower($data['city'])])
            )
            ->first();

        $payload = [
            'name' => trim($data['company_name']),
            'address_text' => $data['address_text'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country' => $data['country'] ?? 'Malaysia',
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'public_display_enabled' => (bool) $data['public_display_enabled'],
            'public_website' => $data['public_website'] ?? null,
        ];

        if ($avatarPath) {
            $payload['avatar_path'] = $avatarPath;
            $payload['avatar_seed'] = null;
        } elseif (! $existing?->avatar_path) {
            $payload['avatar_seed'] = $avatarSeed;
        }

        if ($existing) {
            $payload['public_display_enabled'] = $existing->public_display_enabled
                || $payload['public_display_enabled'];
            $existing->update($payload);

            return $existing->fresh();
        }

        return Company::create($payload);
    }
}
