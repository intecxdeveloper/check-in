<form id="checkin-form" novalidate>
    <div id="checkin-fields" class="checkin-fields">
        <section class="checkin-block checkin-step" aria-labelledby="checkin-you-heading">
            <h3 id="checkin-you-heading" class="checkin-block-title">You</h3>
            <p class="checkin-help">Name stays with staff. Email or phone is enough.</p>

            <label>Your name
                <input type="text" name="full_name" required maxlength="120" autocomplete="name" placeholder="Aisha">
            </label>
            <label>Role <span>optional</span>
                <input type="text" name="job_title" maxlength="120" autocomplete="organization-title" placeholder="GIS lead">
            </label>
            <div class="checkin-grid">
                <label>Email <span>or phone</span>
                    <input type="email" name="email" maxlength="255" autocomplete="email" placeholder="you@company.my">
                </label>
                <label>Phone <span>or email</span>
                    <input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="01x-xxx xxxx">
                </label>
            </div>

            <div class="avatar-studio">
                <div class="avatar-studio-preview">
                    <img id="avatar-preview" class="avatar-preview-img" src="https://api.dicebear.com/9.x/avataaars/svg?seed=AishaCorp&size=96&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf,c1f4c5&radius=50&clothing=blazerAndShirt&clothingColor=25557c&mouth=smile,default,twinkle" alt="Avatar preview" width="96" height="96">
                    <p class="checkin-help" id="avatar-preview-label">Preset · Aisha</p>
                </div>

                <div>
                    <p class="avatar-studio-label">Map look</p>
                    <div id="avatar-presets" class="avatar-preset-grid" role="listbox" aria-label="Preset avatars"></div>

                    <div class="avatar-upload-row">
                        <label class="avatar-upload-btn">
                            <input id="avatar-file" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                            <span>Upload photo / logo</span>
                        </label>
                        <button type="button" id="avatar-clear-upload" class="avatar-clear-btn hidden">Use preset</button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="avatar_seed" id="avatar-seed" value="AishaCorp">
        </section>

        <section class="checkin-block checkin-step" aria-labelledby="checkin-company-heading">
            <h3 id="checkin-company-heading" class="checkin-block-title">Company</h3>
            <p class="checkin-help">Search a Malaysian company, or type yours if it isn’t listed.</p>
            <label>Company
                <input type="text" name="company_name" id="company-name-input" required maxlength="180" autocomplete="off" placeholder="Petronas, Maybank, your Sdn Bhd…">
            </label>
            <ul id="company-results" class="location-results company-suggest" hidden></ul>
            <p id="company-pick-hint" class="checkin-help">Picking a listed company can drop the map pin for you.</p>
            <label>Website <span>optional</span>
                <input type="text" name="public_website" inputmode="url" autocomplete="url" placeholder="company.com" maxlength="255">
            </label>
        </section>

        <section class="checkin-block checkin-step" aria-labelledby="checkin-interests-heading">
            <h3 id="checkin-interests-heading" class="checkin-block-title">Interests</h3>
            <p class="checkin-help">Tap at least one — no wrong answers.</p>
            <div id="interest-options" class="interest-grid" role="group" aria-label="Interests">
                @foreach (collect($services ?? []) as $service)
                    <div class="interest-chip" data-slug="{{ data_get($service, 'slug') }}" style="--interest-color: {{ data_get($service, 'color') ?: '#0058b8' }}">
                        <input type="checkbox" name="services[]" value="{{ data_get($service, 'id') }}" aria-label="{{ data_get($service, 'name') }}">
                        <span class="interest-option-swatch" aria-hidden="true"></span>
                        <span class="interest-option-label">{{ data_get($service, 'name') }}</span>
                    </div>
                @endforeach
            </div>
            <label id="other-interest-wrap" class="hidden">
                What should we call it?
                <input id="other-interest-input" type="text" name="other_interest" maxlength="80" autocomplete="off" placeholder="Type anything — hydrography, drones, CAD…">
            </label>
            @if (collect($services ?? [])->isEmpty())
                <p class="checkin-help" id="interest-empty-hint">Interests are loading… if this stays blank, refresh once.</p>
            @endif
        </section>

        <section class="checkin-block checkin-step" aria-labelledby="checkin-place-heading">
            <h3 id="checkin-place-heading" class="checkin-block-title">Office on the map</h3>
            <p class="checkin-help" id="location-help">Search, use this phone, or tap the map. Then hold the avatar and drag it with your finger.</p>
            <div class="location-banner" id="location-banner" hidden>
                <strong id="location-banner-title">Location ready</strong>
                <p id="location-banner-copy"></p>
                <button type="button" id="location-adjust-btn" class="btn-secondary">Adjust on map</button>
            </div>
            <div id="checkin-map-stage" class="checkin-map-stage">
                <div class="checkin-map-stage-bar">
                    <p class="checkin-map-stage-label">Office map</p>
                    <button type="button" id="checkin-map-fullscreen" class="checkin-map-fullscreen-btn" aria-pressed="false" aria-controls="checkin-map">
                        Open big map
                    </button>
                </div>
                <div class="location-search" id="location-search" role="search">
                    <label for="location-query" class="sr-only">Search for a place in Malaysia</label>
                    <span class="location-search-icon" aria-hidden="true"></span>
                    <input id="location-query" type="search" inputmode="search" enterkeyhint="search" placeholder="Search a place, e.g. Mid Valley, Penang" autocomplete="off"
                        role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="location-results">
                    <button type="button" id="location-search-btn">Search</button>
                </div>
                <button type="button" id="location-gps-btn" class="location-gps-btn">Use my location</button>
                <div class="location-status-row">
                    @include('partials.aiva', ['pose' => 'work', 'size' => 34, 'class' => 'aiva-status'])
                    <p id="location-status" class="location-status" role="status" aria-live="polite" hidden></p>
                </div>
                <ul id="location-results" class="location-results" role="listbox" aria-label="Matching places" hidden></ul>
                <div id="checkin-map" class="checkin-map" role="application" aria-label="Map to place your office"></div>
                <p class="checkin-help">Can’t find it? Tap to drop the pin, then hold and drag. Open the big map if the pin feels small.</p>
                <div class="checkin-map-stage-done">
                    <button type="button" id="checkin-map-done" class="btn-primary">Use this pin</button>
                </div>
            </div>
            <details class="location-details">
                <summary>Fine-tune city and state</summary>
                <div class="checkin-grid">
                    <label>City
                        <input type="text" name="city" maxlength="120" autocomplete="address-level2">
                    </label>
                    <label>State / region
                        <input type="text" name="region" maxlength="120" autocomplete="address-level1">
                    </label>
                    <label>Country
                        <input type="text" name="country" maxlength="120" value="Malaysia" autocomplete="country-name">
                    </label>
                </div>
            </details>
            <input type="hidden" name="latitude">
            <input type="hidden" name="longitude">
            <input type="hidden" name="address_text">
        </section>

        <section class="checkin-block checkin-step" aria-labelledby="checkin-join-heading">
            <h3 id="checkin-join-heading" class="checkin-block-title">Join</h3>
            <div class="consent-card">
                <p><strong>On the map:</strong> your avatar, company, place, interests, and optional message.</p>
                <p><strong>Always private:</strong> your name, email, phone, and staff notes.</p>
            </div>

            <div class="message-composer">
                <label for="display-message-input">Message on your avatar bubble <span>optional</span></label>
                <input
                    id="display-message-input"
                    type="text"
                    name="display_message"
                    maxlength="80"
                    placeholder="What say you? Hello from KL!"
                    autocomplete="off"
                    aria-describedby="message-count"
                >
                <p id="message-count" class="message-count" aria-live="polite">0 / 80</p>
                <div class="message-bubble-preview" aria-hidden="true">
                    <span id="message-bubble-preview-text">Your message will show here</span>
                </div>
            </div>

            <input type="hidden" name="public_display_enabled" value="1">
            <p class="checkin-help">Your avatar always appears on the live booth map.</p>
            <label class="consent-toggle">
                <input type="checkbox" name="follow_up_consent" value="1">
                <span>Happy for a friendly follow-up after the show</span>
            </label>
            <label class="consent-toggle">
                <input type="checkbox" name="privacy_accepted" value="1" required>
                <span>I’ve read the short privacy note (v{{ $privacyVersion }})</span>
            </label>
            <details class="privacy-note">
                <summary>What we keep</summary>
                <p>Used for this booth experience and authorised follow-up, then cleaned up per company policy. Contact details never appear on the public map.</p>
            </details>
        </section>
    </div>

    <section id="checkin-success" class="checkin-success hidden" hidden>
        <div class="success-panel{{ isset($showPrizeReceipt) ? ' success-panel--receipt' : '' }}">
            <div class="success-badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22"><path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="success-figures">
                <img id="success-avatar" class="success-avatar-img" alt="" width="96" height="96">
                @include('partials.aiva', ['pose' => 'wave', 'size' => 76, 'class' => 'aiva-success'])
            </div>
            <h3 id="success-title">Looking good!</h3>
            <p id="success-copy">Look up at the booth screen — your avatar should appear in a few seconds.</p>
            @isset($showPrizeReceipt)
                <div class="prize-receipt" id="prize-receipt" aria-label="Prize claim receipt">
                    <p class="prize-receipt-eyebrow">Prize claim receipt</p>
                    <p class="prize-receipt-code" id="prize-claim-code">CK-————</p>
                    <dl class="prize-receipt-meta">
                        <div>
                            <dt>Name</dt>
                            <dd id="prize-claim-name">—</dd>
                        </div>
                        <div>
                            <dt>Company</dt>
                            <dd id="prize-claim-company">—</dd>
                        </div>
                    </dl>
                    <p class="prize-receipt-cta">Show this screen at the counter to claim your prize.</p>
                </div>
            @endisset
        </div>
    </section>

    <div class="checkin-error-row">
        @include('partials.aiva', ['pose' => 'idle', 'size' => 30, 'class' => 'aiva-error'])
        <p id="checkin-error" class="checkin-error hidden" role="alert"></p>
    </div>

    <div class="checkin-actions">
        <button type="submit" id="checkin-next" class="btn-primary">Join the map</button>
    </div>
</form>
