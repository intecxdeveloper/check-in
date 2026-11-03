@extends('layouts.map')

@section('head')
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="dns-prefetch" href="https://tiles.openfreemap.org">
@endsection

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
            fetchpriority="high"
        >
        <div class="aiva-greeting">
            @include('partials.aiva', ['pose' => 'wave', 'size' => 56, 'loading' => 'eager', 'class' => 'aiva-greeting-img'])
            <p class="aiva-bubble"><strong>Hi, I’m Aiva.</strong> About a minute — then show your receipt at the counter.</p>
        </div>
        <h1>Join the Malaysia map</h1>
        <p>Five short steps. Your name stays private. The pin is only for the booth map.</p>
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
            <ol class="checkin-step-names" aria-hidden="true">
                <li data-step-name="1">You</li>
                <li data-step-name="2">Company</li>
                <li data-step-name="3">Interests</li>
                <li data-step-name="4">Place</li>
                <li data-step-name="5">Join</li>
            </ol>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'showPrizeReceipt' => true, 'services' => $services])
        </div>
    </div>
</div>
@endsection
