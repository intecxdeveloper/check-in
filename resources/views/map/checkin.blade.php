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
        <div class="expo-brand-avatars" aria-hidden="true">
            <img src="https://api.dicebear.com/9.x/avataaars/svg?seed=booth1&backgroundColor=ffd5dc" alt="">
            <img src="https://api.dicebear.com/9.x/avataaars/svg?seed=booth2&backgroundColor=b6e3f4" alt="">
            <img src="https://api.dicebear.com/9.x/avataaars/svg?seed=booth3&backgroundColor=c1f4c5" alt="">
        </div>
        <h1>Join the Malaysia map</h1>
        <p>Pick an avatar, find your company, drop a pin — then watch yourself appear on the big screen.</p>
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
                    <p class="checkin-eyebrow" id="checkin-step-label">1 of 5</p>
                    <h2 id="checkin-title">Your look</h2>
                </div>
            </div>

            <div class="checkin-progress" aria-hidden="true">
                <span data-step-dot="1" class="active"></span>
                <span data-step-dot="2"></span>
                <span data-step-dot="3"></span>
                <span data-step-dot="4"></span>
                <span data-step-dot="5"></span>
                <span data-step-dot="6"></span>
            </div>

            @include('map.partials.checkin-form', ['privacyVersion' => $privacyVersion, 'showMapLink' => true])
        </div>
    </div>
</div>
@endsection
