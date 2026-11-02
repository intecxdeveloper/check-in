@extends('layouts.map')

@section('content')
<div
    id="expo-app"
    class="expo-shell checkin-only"
    data-mode="checkin"
    data-map-style="{{ config('expo.map_style') }}"
    data-privacy-version="{{ $privacyVersion }}"
    data-services='@json($services)'
    data-auto-open-checkin="1"
>
    <div class="checkin-only-bg" aria-hidden="true"></div>

    <div class="checkin-only-hero">
        <img
            class="checkin-brand-logo"
            src="{{ asset(config('expo.brand_logo')) }}"
            alt="{{ config('expo.brand_name') }}"
            width="640"
            height="223"
            decoding="async"
        >
        <h1>Join the Malaysia map</h1>
        <p>Five quick steps, about a minute. When you’re done, show your receipt at the counter to claim a prize.</p>
    </div>

    <div id="company-card" class="company-card hidden" hidden></div>
    <div id="milestone" class="expo-milestone hidden" hidden></div>
    <div id="map-fallback" class="map-fallback hidden" hidden></div>
    <div id="service-filters" class="hidden" hidden></div>
    <ul id="recent-list" class="hidden" hidden></ul>
    <span data-stat="check_ins" class="hidden" hidden>0</span>
    <span data-stat="companies" class="hidden" hidden>0</span>
    <span data-stat="regions" class="hidden" hidden>0</span>

    <div id="checkin-modal" class="checkin-modal checkin-modal-page" role="dialog" aria-modal="true" aria-labelledby="checkin-title">
        <div class="checkin-sheet">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label" aria-live="polite">Step 1 of 5</p>
                    <h2 id="checkin-title" tabindex="-1">About you</h2>
                </div>
            </div>

            <div class="checkin-progress" role="progressbar" aria-label="Check-in progress" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1">
                <span data-step-dot="1" class="active current"></span>
                <span data-step-dot="2"></span>
                <span data-step-dot="3"></span>
                <span data-step-dot="4"></span>
                <span data-step-dot="5"></span>
            </div>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'showPrizeReceipt' => true, 'services' => $services])
        </div>
    </div>
</div>
@endsection
