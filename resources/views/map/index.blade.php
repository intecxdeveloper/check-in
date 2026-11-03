@extends('layouts.map')

@section('content')
<div
    id="expo-app"
    class="expo-shell"
    data-map-style="{{ config('expo.map_style') }}"
    data-map-pitch="{{ config('expo.map_pitch') }}"
    data-map-max-pitch="{{ config('expo.map_max_pitch') }}"
    data-map-bearing="{{ config('expo.map_bearing') }}"
    data-map-terrain="{{ config('expo.map_terrain') ? '1' : '0' }}"
    data-map-terrain-exaggeration="{{ config('expo.map_terrain_exaggeration') }}"
    data-map-center-lng="{{ $mapCenterLng }}"
    data-map-center-lat="{{ $mapCenterLat }}"
    data-map-start-zoom="{{ config('expo.map_start_zoom') }}"
    data-map-max-zoom="{{ config('expo.map_max_zoom') }}"
    data-privacy-version="{{ $privacyVersion }}"
    data-services='@json($services)'
    data-check-in-url="{{ $checkInUrl }}"
>
    <a class="skip-link" href="{{ route('check-in') }}">Skip to check-in</a>

    <div id="map" class="expo-map" role="application" aria-label="Live visitor map of Malaysia"></div>
    <div class="expo-vignette" aria-hidden="true"></div>
    <p id="map-loading" class="map-loading" role="status">Opening the Malaysia map…</p>

    <div class="expo-overlay pointer-events-none">
        <header class="expo-brand pointer-events-auto">
            <a class="expo-brand-link" href="{{ route('map') }}" aria-label="{{ config('expo.brand_name') }}">
                <img
                    class="expo-brand-logo"
                    src="{{ asset(config('expo.brand_logo')) }}"
                    alt="{{ config('expo.brand_name') }}"
                    width="640"
                    height="223"
                    decoding="async"
                    fetchpriority="high"
                >
            </a>
            <p class="expo-brand-tag">Live visitor map</p>
        </header>

        <div class="expo-pulse pointer-events-auto" aria-label="Live totals">
            <div>
                <strong data-stat="check_ins">0</strong>
                <span>check-ins</span>
            </div>
            <div>
                <strong data-stat="companies">0</strong>
                <span>companies</span>
            </div>
            <div>
                <strong data-stat="regions">0</strong>
                <span>places</span>
            </div>
        </div>

        <aside class="expo-live is-hidden" id="expo-live" aria-label="Recent check-ins" aria-hidden="true">
            <p class="expo-live-label">Just joined</p>
            <ul id="recent-list" class="expo-ticker-list">
                <li class="expo-ticker-empty"><small>Be the first pin on the map.</small></li>
            </ul>
        </aside>

        <aside class="expo-qr pointer-events-auto">
            <canvas id="checkin-qr" width="148" height="148" aria-label="QR code for check-in"></canvas>
            <p class="expo-qr-caption">Scan to check in</p>
            <a id="checkin-qr-link" class="expo-qr-link" href="{{ $checkInUrl }}">Open on this phone</a>
            <button type="button" id="checkin-open" class="expo-cta">Check in here</button>
        </aside>
        <div id="service-filters" class="hidden" hidden></div>
    </div>

    <a class="expo-mobile-cta" href="{{ route('check-in') }}">Check in on this phone</a>

    <div id="company-card" class="company-card hidden" role="dialog" aria-modal="true" aria-labelledby="company-card-title">
        <button type="button" id="company-card-close" class="company-card-close" aria-label="Close">×</button>
        <div class="company-card-hero">
            <img id="company-card-avatar" class="company-card-avatar" alt="" width="64" height="64">
            <div>
                <h2 id="company-card-title"></h2>
                <p id="company-card-location" class="company-card-location"></p>
            </div>
        </div>
        <div id="company-card-services" class="expo-chips"></div>
        <a id="company-card-website" class="company-card-link hidden" href="#" target="_blank" rel="noopener">Visit website</a>
        <p id="company-card-count" class="company-card-meta"></p>
    </div>

    <div id="milestone" class="expo-milestone hidden" role="status"></div>
    <canvas id="join-fireworks" class="join-fireworks hidden" aria-hidden="true"></canvas>
    <div id="checkin-toast" class="join-toast hidden" role="status" aria-live="assertive"></div>
    <div id="map-fallback" class="map-fallback hidden"></div>

    <div id="checkin-modal" class="checkin-modal hidden" role="dialog" aria-modal="true" aria-labelledby="checkin-title">
        <div class="checkin-sheet">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label" aria-live="polite">Step 1 of 5</p>
                    <h2 id="checkin-title" tabindex="-1">About you</h2>
                </div>
                <button type="button" id="checkin-close" class="checkin-close" aria-label="Close check-in">×</button>
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

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'services' => $services])
        </div>
    </div>
</div>
@endsection
