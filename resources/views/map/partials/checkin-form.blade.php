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
                        <input id="avatar-file" type="file" name="avatar" accept="image/*" capture="environment" hidden>
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
                {{-- Use div chips (not label) so phone CSS for form labels cannot hide them. --}}
                <div
                    class="interest-chip"
                    style="--interest-color: {{ $service->color ?: '#007aff' }}; display:flex; align-items:center; gap:0.65rem; min-height:54px; padding:0.85rem 0.95rem; margin:0; border-radius:1rem; border:1.5px solid rgba(0,0,0,.12); background:#f5f5f7; color:#1d1d1f; font-weight:700; font-size:1rem;"
                >
                    <input type="checkbox" name="services[]" value="{{ $service->id }}" style="width:1.2rem;height:1.2rem;flex:none;margin:0;">
                    <span class="interest-option-swatch" aria-hidden="true" style="width:.7rem;height:.7rem;border-radius:999px;background:{{ $service->color ?: '#007aff' }};flex:none;"></span>
                    <span class="interest-option-label" style="color:#1d1d1f;font-weight:700;">{{ $service->name }}</span>
                </div>
            @endforeach
        </div>
        @if (($services ?? collect())->isEmpty())
            <p class="checkin-help" id="interest-empty-hint">Interests are loading… if this stays blank, refresh once.</p>
        @endif
    </section>

    <section data-step="4" class="checkin-step hidden">
        <p class="checkin-help" id="location-help">If we know your company pin, confirm it. Otherwise search a place or tap the map.</p>
        <div class="location-banner" id="location-banner" hidden>
            <strong id="location-banner-title">Location ready</strong>
            <p id="location-banner-copy"></p>
            <button type="button" id="location-adjust-btn" class="btn-secondary">Adjust on map</button>
        </div>
        <div class="location-search">
            <input id="location-query" type="search" placeholder="KL, Penang, JB, Kuching…" autocomplete="off">
            <button type="button" id="location-search-btn">Search</button>
        </div>
        <ul id="location-results" class="location-results"></ul>
        <div id="checkin-map" class="checkin-map"></div>
        <p class="checkin-help">Drag the map to look around — your avatar stays pinned. Drag the avatar to fine-tune.</p>
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
        <div class="success-panel">
            <img id="success-avatar" class="success-avatar-img" alt="" width="96" height="96">
            <p class="checkin-eyebrow">You’re in</p>
            <h3 id="success-title">Looking good!</h3>
            <p id="success-copy">Look up at the booth screen — your avatar should appear in a few seconds.</p>
            @isset($showMapLink)
                <a class="btn-primary" href="{{ route('map') }}" style="display:inline-flex;margin-top:1rem;text-decoration:none;align-items:center">See the map</a>
            @endisset
        </div>
    </section>

    <p id="checkin-error" class="checkin-error hidden" role="alert"></p>

    <div class="checkin-actions">
        <button type="button" id="checkin-back" class="btn-secondary hidden">Back</button>
        <button type="button" id="checkin-next" class="btn-primary">Continue</button>
    </div>
</form>
