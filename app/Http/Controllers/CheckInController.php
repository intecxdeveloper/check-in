<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckInRequest;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Visitor;
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

        $result = DB::transaction(function () use ($data, $fingerprint) {
            $company = $this->resolveCompany($data);

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

            if ($company->public_display_enabled) {
                $company->latest_public_message = $visitor->display_message ?? '';
            }

            $firstName = trim(explode(' ', trim((string) $visitor->full_name))[0] ?? '');

            return [
                'visitor_id' => $visitor->id,
                'first_name' => $firstName !== '' ? $firstName : null,
                'display_message' => $visitor->display_message,
                'company' => $company->public_display_enabled
                    ? $company->toPublicMapFeature()
                    : null,
                'public_display_enabled' => $company->public_display_enabled,
            ];
        });

        return response()->json([
            'message' => 'Welcome to the network!',
            'duplicate' => false,
            ...$result,
        ], 201);
    }

    private function resolveCompany(array $data): Company
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
            'country' => $data['country'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'public_display_enabled' => (bool) $data['public_display_enabled'],
            'public_website' => $data['public_website'] ?? null,
        ];

        if ($existing) {
            // Prefer public display if any visitor opts in; keep latest confirmed pin.
            $payload['public_display_enabled'] = $existing->public_display_enabled
                || $payload['public_display_enabled'];
            $existing->update($payload);

            return $existing->fresh();
        }

        return Company::create($payload);
    }
}
