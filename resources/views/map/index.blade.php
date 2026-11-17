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
    data-gift-prizes='@json($giftPrizes)'
    data-gift-spin-url="{{ route('api.gift-spin') }}"
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

        <aside class="expo-invite" id="expo-invite" aria-label="Come check in. Collect a gift.">
            @include('partials.aiva', ['pose' => 'wave', 'size' => 72, 'class' => 'expo-invite-aiva', 'loading' => 'eager'])
            <div class="expo-invite-copy">
                <p class="expo-invite-title">Come check in. Collect a gift.</p>
                <p class="expo-invite-text">One page on your phone. Then spin the NADI wheel at this booth.</p>
            </div>
        </aside>

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
            <button type="button" id="gift-open" class="expo-gift-open">Spin for a gift</button>
        </aside>
        <div id="service-filters" class="hidden" hidden></div>
    </div>

    <div class="expo-mobile-actions">
        <a class="expo-mobile-cta" href="{{ route('check-in') }}">Check in</a>
        <button type="button" id="gift-open-mobile" class="expo-mobile-gift">Gift wheel</button>
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
    <canvas id="join-fireworks" class="join-fireworks hidden" aria-hidden="true"></canvas>
    <div id="checkin-toast" class="join-toast hidden" role="status" aria-live="assertive"></div>
    <div id="map-fallback" class="map-fallback hidden"></div>

    <div id="gift-modal" class="gift-modal hidden" role="dialog" aria-modal="true" aria-labelledby="gift-title">
        <div class="gift-sheet">
            <div class="gift-header">
                <div>
                    <p class="gift-eyebrow">Booth gift</p>
                    <h2 id="gift-title">Spin the NADI wheel</h2>
                </div>
                <button type="button" id="gift-close" class="checkin-close" aria-label="Close gift wheel">×</button>
            </div>
            <p class="gift-help">Type the claim code from check-in, then spin once for a booth gift. Staff can type the master key from Gifts if a receipt is missing.</p>
            <form id="gift-form" class="gift-form">
                <label class="gift-code-label" for="gift-claim-code">Claim code</label>
                <input id="gift-claim-code" name="claim_code" type="text" inputmode="text" autocomplete="off" maxlength="20" placeholder="CK-0001" required>
                <div class="gift-stage">
                    <div class="gift-pointer" aria-hidden="true"></div>
                    <div id="gift-wheel" class="gift-wheel" aria-hidden="true"></div>
                    <span class="gift-hub" aria-hidden="true"></span>
                </div>
                <p id="gift-status" class="gift-status" role="status"></p>
                <button type="submit" id="gift-spin" class="expo-cta">Spin</button>
            </form>
            <div id="gift-result" class="gift-result hidden" role="status">
                <p class="gift-result-kicker" id="gift-result-kicker">You won</p>
                <p class="gift-result-prize" id="gift-result-prize"></p>
                <p class="gift-result-copy" id="gift-result-copy">Show this screen at the counter to collect it.</p>
            </div>
        </div>
    </div>

    <div id="checkin-modal" class="checkin-modal hidden" role="dialog" aria-modal="true" aria-labelledby="checkin-title">
        <div class="checkin-sheet" data-mode="form">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label" aria-live="polite">One page</p>
                    <h2 id="checkin-title" tabindex="-1">Check in</h2>
                </div>
                <button type="button" id="checkin-close" class="checkin-close" aria-label="Close check-in">×</button>
            </div>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'services' => $services])
        </div>
    </div>
</div>
@endsection
