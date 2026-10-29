@extends('layouts.map')

@section('content')
<div
    id="expo-app"
    class="expo-shell"
    data-map-style="{{ config('expo.map_style') }}"
    data-privacy-version="{{ $privacyVersion }}"
    data-services='@json($services)'
    data-check-in-url="{{ $checkInUrl }}"
>
    <div id="map" class="expo-map" aria-label="Interactive visitor globe"></div>
    <div class="expo-vignette" aria-hidden="true"></div>

    <div class="expo-overlay pointer-events-none">
        <header class="expo-brand pointer-events-auto">
            <p class="expo-brand-name">Our World</p>
        </header>

        <aside class="expo-qr pointer-events-auto">
            <canvas id="checkin-qr" width="148" height="148" aria-label="QR code for check-in"></canvas>
            <p class="expo-qr-caption">Scan to check in</p>
            <a id="checkin-qr-link" class="expo-qr-link" href="{{ $checkInUrl }}">Open link</a>
            <button type="button" id="checkin-open" class="expo-cta">Check in here</button>
        </aside>

        <span data-stat="check_ins" class="hidden" hidden>0</span>
        <span data-stat="companies" class="hidden" hidden>0</span>
        <span data-stat="regions" class="hidden" hidden>0</span>
        <div id="service-filters" class="hidden" hidden></div>
        <ul id="recent-list" class="hidden" hidden aria-hidden="true"></ul>
    </div>

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
    <div id="checkin-toast" class="join-toast hidden" role="status" aria-live="assertive"></div>
    <div id="map-fallback" class="map-fallback hidden"></div>

    <div id="checkin-modal" class="checkin-modal hidden" role="dialog" aria-modal="true" aria-labelledby="checkin-title">
        <div class="checkin-sheet">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label">1 of 5</p>
                    <h2 id="checkin-title">Your look</h2>
                </div>
                <button type="button" id="checkin-close" class="checkin-close" aria-label="Close check-in">×</button>
            </div>

            <div class="checkin-progress" aria-hidden="true">
                <span data-step-dot="1" class="active"></span>
                <span data-step-dot="2"></span>
                <span data-step-dot="3"></span>
                <span data-step-dot="4"></span>
                <span data-step-dot="5"></span>
                <span data-step-dot="6"></span>
            </div>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'services' => $services])
        </div>
    </div>
</div>
@endsection
