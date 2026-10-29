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
    <div id="checkin-toast" class="join-celebration hidden" role="status" aria-live="assertive"></div>
    <div id="map-fallback" class="map-fallback hidden"></div>

    <div id="checkin-modal" class="checkin-modal hidden" role="dialog" aria-modal="true" aria-labelledby="checkin-title">
        <div class="checkin-sheet">
            <div class="checkin-header">
                <div>
                    <p class="checkin-eyebrow" id="checkin-step-label">1 of 4</p>
                    <h2 id="checkin-title">Create your pin</h2>
                </div>
                <button type="button" id="checkin-close" class="checkin-close" aria-label="Close check-in">×</button>
            </div>

            <div class="checkin-progress" aria-hidden="true">
                <span data-step-dot="1" class="active"></span>
                <span data-step-dot="2"></span>
                <span data-step-dot="3"></span>
                <span data-step-dot="4"></span>
                <span data-step-dot="5"></span>
            </div>

            <form id="checkin-form" novalidate>
                <section data-step="1" class="checkin-step">
                    <p class="checkin-help">No account needed — just a colorful avatar for your company.</p>
                    <label>Your name<input name="full_name" required maxlength="120" autocomplete="name" placeholder="Aisha"></label>
                    <label>Company
                        <input name="company_name" id="company-name-input" required maxlength="180" autocomplete="off" placeholder="Search or type your company">
                    </label>
                    <ul id="company-results" class="location-results" hidden></ul>
                    <label>Role <span>optional</span><input name="job_title" maxlength="120" autocomplete="organization-title" placeholder="GIS lead"></label>
                    <div class="checkin-grid">
                        <label>Email<input type="email" name="email" maxlength="255" autocomplete="email" placeholder="you@company.my"></label>
                        <label>Phone<input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="01x-xxx xxxx"></label>
                    </div>
                    <p class="checkin-help">Email or phone stays private — never on the public globe.</p>
                </section>

                <section data-step="2" class="checkin-step hidden">
                    <p class="checkin-help">Pick what you’re into. Tap as many as you like.</p>
                    <div id="interest-options" class="interest-grid"></div>
                </section>

                <section data-step="3" class="checkin-step hidden">
                    <p class="checkin-help">Search a place, or tap the map — your avatar sticks to that spot.</p>
                    <div class="location-search">
                        <input id="location-query" type="search" placeholder="Company, KL, Penang, JB…" autocomplete="off">
                        <button type="button" id="location-search-btn">Search</button>
                    </div>
                    <ul id="location-results" class="location-results"></ul>
                    <div id="checkin-map" class="checkin-map"></div>
                    <p class="checkin-help">Drag the map to look around — the avatar stays pinned. Drag the avatar to fine-tune.</p>
                    <div class="checkin-grid">
                        <label>City<input name="city" maxlength="120"></label>
                        <label>State / region<input name="region" maxlength="120"></label>
                        <label>Country<input name="country" maxlength="120" value="Malaysia"></label>
                    </div>
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">
                    <input type="hidden" name="address_text">
                </section>

                <section data-step="4" class="checkin-step hidden">
                    <div class="consent-card">
                        <p><strong>On the globe</strong> (if you opt in): company name, place, interests, optional message & website.</p>
                        <p><strong>Always private:</strong> your name, email, phone, and staff notes.</p>
                    </div>
                    <label>Say hi on the map <span>optional</span>
                        <input name="display_message" maxlength="80" placeholder="Hello from Penang!" autocomplete="off">
                    </label>
                    <label class="consent-toggle consent-toggle-fun">
                        <input type="checkbox" name="public_display_enabled" value="1" checked>
                        <span>Show my company avatar on the live globe</span>
                    </label>
                    <label>Website <span>optional</span><input type="url" name="public_website" placeholder="https://" maxlength="255"></label>
                    <label class="consent-toggle">
                        <input type="checkbox" name="follow_up_consent" value="1">
                        <span>Happy for a friendly follow-up after the show</span>
                    </label>
                    <label class="consent-toggle">
                        <input type="checkbox" name="privacy_accepted" value="1" required>
                        <span>I’ve read the short privacy note (v{{ $privacyVersion }})</span>
                    </label>
                    <p class="checkin-help">Used for this booth experience and authorised follow-up, then cleaned up per company policy.</p>
                </section>

                <section data-step="5" class="checkin-step hidden">
                    <div class="success-panel">
                        <img id="success-avatar" class="success-avatar-img" alt="" width="96" height="96">
                        <p class="checkin-eyebrow">You’re in</p>
                        <h3 id="success-title">Looking good!</h3>
                        <p id="success-copy">Your people avatar is joining the Malaysia globe.</p>
                    </div>
                </section>

                <p id="checkin-error" class="checkin-error hidden" role="alert"></p>

                <div class="checkin-actions">
                    <button type="button" id="checkin-back" class="btn-secondary hidden">Back</button>
                    <button type="button" id="checkin-next" class="btn-primary">Continue</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
