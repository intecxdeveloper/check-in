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
            <p class="aiva-bubble"><strong>Hi, I’m Aiva.</strong> A few taps — then show your receipt at the counter.</p>
        </div>
        <h1>Join the Malaysia map</h1>
        <p>Your name stays private. A city is enough for the booth map.</p>
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
        <div class="checkin-sheet" data-mode="form">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label" aria-live="polite">One page</p>
                    <h2 id="checkin-title" tabindex="-1">Check in</h2>
                </div>
            </div>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'showPrizeReceipt' => true, 'services' => $services])
        </div>
    </div>
</div>
@endsection
