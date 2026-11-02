<form id="checkin-form" novalidate>
    <section data-step="1" class="checkin-step">
        <p class="checkin-help">No account needed. Pick a look — preset, your face, or a company logo.</p>

        <div class="avatar-studio">
            <div class="avatar-studio-preview">
                <img id="avatar-preview" class="avatar-preview-img" src="https://api.dicebear.com/9.x/avataaars/svg?seed=AishaCorp&size=96&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf,c1f4c5&radius=50&clothing=blazerAndShirt&clothingColor=25557c&mouth=smile,default,twinkle" alt="Avatar preview" width="96" height="96">
                <p class="checkin-help" id="avatar-preview-label">Preset · Aisha</p>
            </div>

            <div>
                <p class="avatar-studio-label">Preset avatars</p>
                <div id="avatar-presets" class="avatar-preset-grid" role="listbox" aria-label="Preset avatars"></div>

                <div class="avatar-upload-row">
                    <label class="avatar-upload-btn">
                        <input id="avatar-file" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" hidden>
                        <span>Upload photo / logo</span>
                    </label>
                    <button type="button" id="avatar-clear-upload" class="avatar-clear-btn hidden">Use preset</button>
                </div>
                <p class="checkin-help">Phone photos and logos are welcome. Keep it friendly for a public screen.</p>
            </div>
        </div>

        <input type="hidden" name="avatar_seed" id="avatar-seed" value="AishaCorp">

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
        <p class="checkin-help">Private to staff — never shown on the public map.</p>
    </section>

    <section data-step="2" class="checkin-step hidden">
        <p class="checkin-help">Search Malaysian companies, or just type yours if it’s not listed.</p>
        <label>Company
            <input type="text" name="company_name" id="company-name-input" required maxlength="180" autocomplete="off" placeholder="Start typing — Petronas, Maybank, your Sdn Bhd…">
        </label>
        <ul id="company-results" class="location-results company-suggest" hidden></ul>
        <p id="company-pick-hint" class="checkin-help">Tip: picking a listed company can auto-place you on the map.</p>
        <label>Website <span>optional</span>
            <input type="url" name="public_website" placeholder="https://" maxlength="255">
        </label>
    </section>

    <section data-step="3" class="checkin-step hidden">
        <p class="checkin-help">What are you into? Tap as many as you like — no wrong answers.</p>
        <div id="interest-options" class="interest-grid" role="group" aria-label="Interests">
            @foreach (($services ?? collect()) as $service)
                <div class="interest-chip" style="--interest-color: {{ $service->color ?: '#007aff' }}">
                    <input type="checkbox" name="services[]" value="{{ $service->id }}" aria-label="{{ $service->name }}">
                    <span class="interest-option-swatch" aria-hidden="true"></span>
                    <span class="interest-option-label">{{ $service->name }}</span>
                </div>
            @endforeach
        </div>
        @if (($services ?? collect())->isEmpty())
            <p class="checkin-help" id="interest-empty-hint">Interests are loading… if this stays blank, refresh once.</p>
        @endif
    </section>

    <section data-step="4" class="checkin-step hidden">
        <p class="checkin-help" id="location-help">Search for your office or area, or tap the map to drop your pin.</p>
        <div class="location-banner" id="location-banner" hidden>
            <strong id="location-banner-title">Location ready</strong>
            <p id="location-banner-copy"></p>
            <button type="button" id="location-adjust-btn" class="btn-secondary">Adjust on map</button>
        </div>
        <div class="location-search" id="location-search" role="search">
            <label for="location-query" class="sr-only">Search for a place in Malaysia</label>
            <span class="location-search-icon" aria-hidden="true"></span>
            <input id="location-query" type="search" inputmode="search" enterkeyhint="search" placeholder="Search a place, e.g. Mid Valley, Penang" autocomplete="off"
                role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="location-results">
            <button type="button" id="location-search-btn">Search</button>
        </div>
        <p id="location-status" class="location-status" role="status" aria-live="polite" hidden></p>
        <ul id="location-results" class="location-results" role="listbox" aria-label="Matching places" hidden></ul>
        <div id="checkin-map" class="checkin-map"></div>
        <p class="checkin-help">Can’t find it? Tap the map near your area, then drag your avatar to the exact spot.</p>
        <div class="checkin-grid">
            <label>City
                <input type="text" name="city" maxlength="120">
            </label>
            <label>State / region
                <input type="text" name="region" maxlength="120">
            </label>
            <label>Country
                <input type="text" name="country" maxlength="120" value="Malaysia">
            </label>
        </div>
        <input type="hidden" name="latitude">
        <input type="hidden" name="longitude">
        <input type="hidden" name="address_text">
    </section>

    <section data-step="5" class="checkin-step hidden">
        <div class="consent-card">
            <p><strong>On the map</strong> (if you opt in): company, place, interests, avatar, optional message.</p>
            <p><strong>Always private:</strong> your name, email, phone, and staff notes.</p>
        </div>

        <div class="message-composer">
            <label for="display-message-input">Message on your avatar bubble <span>optional</span></label>
            <input
                id="display-message-input"
                type="text"
                name="display_message"
                maxlength="80"
                placeholder="Hello from Penang!"
                autocomplete="off"
            >
            <div class="message-bubble-preview" aria-hidden="true">
                <span id="message-bubble-preview-text">Your message will show here</span>
            </div>
            <p class="checkin-help">This short line floats above your avatar on the booth map.</p>
        </div>

        <label class="consent-toggle consent-toggle-fun">
            <input type="checkbox" name="public_display_enabled" value="1" checked>
            <span>Show my avatar on the live booth map</span>
        </label>
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

    <section data-step="6" class="checkin-step hidden">
        <div class="success-panel{{ isset($showPrizeReceipt) ? ' success-panel--receipt' : '' }}">
            <div class="success-badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22"><path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <img id="success-avatar" class="success-avatar-img" alt="" width="96" height="96">
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

    <p id="checkin-error" class="checkin-error hidden" role="alert"></p>

    <div class="checkin-actions">
        <button type="button" id="checkin-back" class="btn-secondary hidden">Back</button>
        <button type="button" id="checkin-next" class="btn-primary">Continue</button>
    </div>
</form>
