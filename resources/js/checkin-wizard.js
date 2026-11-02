import { PRESET_AVATARS, avatarUrl, presetAvatarUrl } from './avatars';

export function createCheckInWizard({ root, maplibregl, services, onSuccess, lockOpen = false }) {
    const modal = root.querySelector('#checkin-modal');
    const form = root.querySelector('#checkin-form');
    const errorEl = root.querySelector('#checkin-error');
    const nextBtn = root.querySelector('#checkin-next');
    const backBtn = root.querySelector('#checkin-back');
    const interestOptions = root.querySelector('#interest-options');
    const locationResults = root.querySelector('#location-results');
    const companyInput = root.querySelector('#company-name-input');
    const companyResults = root.querySelector('#company-results');
    const avatarPresets = root.querySelector('#avatar-presets');
    const avatarPreview = root.querySelector('#avatar-preview');
    const avatarPreviewLabel = root.querySelector('#avatar-preview-label');
    const avatarSeedInput = root.querySelector('#avatar-seed');
    const avatarFileInput = root.querySelector('#avatar-file');
    const avatarClearBtn = root.querySelector('#avatar-clear-upload');
    const locationBanner = root.querySelector('#location-banner');
    const locationHelp = root.querySelector('#location-help');

    let step = 1;
    let stepHasRendered = false;
    let checkinMap = null;
    let pinMarker = null;
    let submitting = false;
    let companySearchTimer = null;
    let companySearchAbort = null;
    let reverseTimer = null;
    let selectedCompany = null;
    let uploadedObjectUrl = null;
    let uploadedFile = null;
    let selectedPreset = PRESET_AVATARS[0];

    renderInterestOptions(services);
    bindInterestChips();
    ensureInterestOptions();

    renderAvatarPresets();
    setPresetAvatar(selectedPreset);

    root.querySelector('#checkin-close')?.addEventListener('click', close);
    const locationQuery = root.querySelector('#location-query');
    const locationStatus = root.querySelector('#location-status');
    let locationTimer = null;
    let locationAbort = null;
    let locationRows = [];
    let locationActive = -1;
    let lastLocationQuery = '';

    root.querySelector('#location-search')?.addEventListener('submit', (event) => event.preventDefault());
    root.querySelector('#location-search-btn')?.addEventListener('click', () => searchLocation({ force: true }));
    locationQuery?.addEventListener('input', () => {
        clearTimeout(locationTimer);
        const q = locationQuery.value.trim();
        if (q.length < 3) {
            locationAbort?.abort();
            renderLocationRows([]);
            setLocationStatus(q.length ? 'Keep typing — 3 letters is enough.' : '');
            return;
        }
        locationTimer = setTimeout(() => searchLocation(), 450);
    });
    locationQuery?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (! locationRows.length) return;
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            setLocationActive((locationActive + delta + locationRows.length) % locationRows.length);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (locationActive >= 0 && locationRows[locationActive]) {
                pickLocation(locationActive);
            } else {
                clearTimeout(locationTimer);
                searchLocation({ force: true });
            }
        } else if (event.key === 'Escape') {
            renderLocationRows([]);
        }
    });
    locationResults?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-result-index]');
        if (button) pickLocation(Number(button.dataset.resultIndex));
    });
    root.querySelector('#location-adjust-btn')?.addEventListener('click', () => {
        locationBanner.hidden = true;
        ensureCheckinMap();
    });

    companyInput?.addEventListener('input', () => {
        selectedCompany = null;
        clearTimeout(companySearchTimer);
        companySearchTimer = setTimeout(() => searchCompanies(companyInput.value.trim()), 350);
    });

    companyInput?.addEventListener('focus', () => {
        if (companyInput.value.trim().length >= 1) {
            searchCompanies(companyInput.value.trim());
        }
    });

    document.addEventListener('click', (event) => {
        if (! companyResults) return;
        if (event.target === companyInput || companyResults.contains(event.target)) return;
        companyResults.hidden = true;
    });

    avatarFileInput?.addEventListener('change', () => {
        const file = avatarFileInput.files?.[0];
        if (! file) return;
        if (uploadedObjectUrl) URL.revokeObjectURL(uploadedObjectUrl);
        uploadedFile = file;
        uploadedObjectUrl = URL.createObjectURL(file);
        avatarPreview.src = uploadedObjectUrl;
        avatarPreviewLabel.textContent = 'Your upload · face or logo';
        avatarSeedInput.value = '';
        avatarClearBtn?.classList.remove('hidden');
        avatarPresets?.querySelectorAll('.avatar-preset').forEach((el) => el.classList.remove('active'));
    });

    avatarClearBtn?.addEventListener('click', () => {
        clearUpload();
        setPresetAvatar(selectedPreset || PRESET_AVATARS[0]);
    });

    const messageInput = root.querySelector('#display-message-input') || form.querySelector('[name="display_message"]');
    const messagePreview = root.querySelector('#message-bubble-preview-text');
    messageInput?.addEventListener('input', () => {
        const text = messageInput.value.trim();
        if (messagePreview) {
            messagePreview.textContent = text || 'Your message will show here';
        }
    });

    nextBtn.addEventListener('click', async () => {
        hideError();
        if (step < 5) {
            if (! validateStep(step)) return;
            goTo(step + 1);
            return;
        }
        if (step === 5) {
            if (! validateStep(5)) return;
            await submit();
            return;
        }
        if (hasPrizeReceipt()) {
            return;
        }
        if (lockOpen) {
            nextBtn.textContent = 'Done — look at the big screen';
            return;
        }
        close();
    });

    backBtn?.addEventListener('click', () => {
        hideError();
        if (step > 1 && step < 6) goTo(step - 1);
    });

    function open() {
        if (step !== 6) resetForm();
        modal.classList.remove('hidden');
        goTo(step === 6 ? 6 : 1);
    }

    function close() {
        if (lockOpen && step === 6) return;
        modal.classList.add('hidden');
        destroyMap();
    }

    function resetForm() {
        form.reset();
        locationResults.innerHTML = '';
        locationResults.hidden = true;
        if (locationStatus) locationStatus.hidden = true;
        if (companyResults) {
            companyResults.innerHTML = '';
            companyResults.hidden = true;
        }
        selectedCompany = null;
        clearUpload();
        setPresetAvatar(PRESET_AVATARS[0]);
        form.querySelector('[name="country"]').value = 'Malaysia';
        form.querySelector('[name="public_display_enabled"]').checked = true;
        hideError();
        submitting = false;
        nextBtn.disabled = false;
        form.querySelector('[name="latitude"]').value = '';
        form.querySelector('[name="longitude"]').value = '';
        if (locationBanner) locationBanner.hidden = true;
        destroyMap();
    }

    function destroyMap() {
        if (checkinMap) {
            checkinMap.remove();
            checkinMap = null;
            pinMarker = null;
        }
    }

    function clearUpload() {
        if (uploadedObjectUrl) URL.revokeObjectURL(uploadedObjectUrl);
        uploadedObjectUrl = null;
        uploadedFile = null;
        if (avatarFileInput) avatarFileInput.value = '';
        avatarClearBtn?.classList.add('hidden');
    }

    function renderInterestOptions(list) {
        if (! interestOptions || ! Array.isArray(list) || ! list.length) {
            return;
        }

        interestOptions.innerHTML = list.map((service) => {
            const color = escapeHtml(service.color || '#0058b8');
            return `
                <div class="interest-chip" style="--interest-color:${color}">
                    <input type="checkbox" name="services[]" value="${escapeHtml(String(service.id))}" aria-label="${escapeHtml(service.name)}">
                    <span class="interest-option-swatch" aria-hidden="true"></span>
                    <span class="interest-option-label">${escapeHtml(service.name)}</span>
                </div>
            `;
        }).join('');

        bindInterestChips();
        root.querySelector('#interest-empty-hint')?.remove();
    }

    function bindInterestChips() {
        if (! interestOptions || interestOptions.dataset.bound === '1') {
            return;
        }
        interestOptions.dataset.bound = '1';
        interestOptions.addEventListener('click', (event) => {
            const chip = event.target.closest('.interest-chip');
            if (! chip || ! interestOptions.contains(chip)) {
                return;
            }
            if (event.target.matches('input[type="checkbox"]')) {
                chip.classList.toggle('is-selected', event.target.checked);
                return;
            }
            const input = chip.querySelector('input[type="checkbox"]');
            if (! input) {
                return;
            }
            input.checked = ! input.checked;
            chip.classList.toggle('is-selected', input.checked);
        });
    }

    async function ensureInterestOptions() {
        if (! interestOptions) {
            return;
        }

        if (interestOptions.querySelectorAll('input[name="services[]"]').length) {
            return;
        }

        try {
            const data = await window.expoFetch('/api/services');
            renderInterestOptions(data.services || []);
        } catch (_) {
            // Server-rendered options (or empty) remain as-is.
        }
    }

    function renderAvatarPresets() {
        if (! avatarPresets) return;
        avatarPresets.innerHTML = PRESET_AVATARS.map((preset) => `
            <button type="button" class="avatar-preset" data-seed="${escapeHtml(preset.seed)}" aria-label="${escapeHtml(preset.label)}">
                <img src="${presetAvatarUrl(preset, 96)}" alt="" width="56" height="56" loading="lazy">
            </button>
        `).join('');

        avatarPresets.querySelectorAll('.avatar-preset').forEach((button) => {
            button.addEventListener('click', () => {
                clearUpload();
                const preset = PRESET_AVATARS.find((item) => item.seed === button.dataset.seed) || PRESET_AVATARS[0];
                setPresetAvatar(preset);
            });
        });
    }

    function setPresetAvatar(presetOrSeed) {
        const preset = typeof presetOrSeed === 'string'
            ? (PRESET_AVATARS.find((item) => item.seed === presetOrSeed) || {
                seed: presetOrSeed,
                label: presetOrSeed,
            })
            : presetOrSeed;

        selectedPreset = preset;
        avatarSeedInput.value = preset.seed;
        avatarPreview.src = preset.clothing
            ? presetAvatarUrl(preset, 160)
            : avatarUrl(preset.seed, 160);
        avatarPreviewLabel.textContent = `Preset · ${preset.label || preset.seed}`;
        avatarPresets?.querySelectorAll('.avatar-preset').forEach((el) => {
            el.classList.toggle('active', el.dataset.seed === preset.seed);
        });
    }

    const stepCopy = {
        1: { label: 'Step 1 of 5', title: 'About you', next: 'Continue' },
        2: { label: 'Step 2 of 5', title: 'Your company', next: 'Continue' },
        3: { label: 'Step 3 of 5', title: 'Your interests', next: 'Continue' },
        4: { label: 'Step 4 of 5', title: 'Where you’re based', next: 'Continue' },
        5: { label: 'Step 5 of 5', title: 'Review and join', next: 'Join the map' },
        6: { label: 'Done', title: 'Looking good!', next: 'Back to the map' },
    };

    function hasPrizeReceipt() {
        return Boolean(root.querySelector('#prize-receipt'));
    }

    function goTo(nextStep) {
        step = nextStep;
        form.querySelectorAll('[data-step]').forEach((section) => {
            section.classList.toggle('hidden', Number(section.dataset.step) !== step);
        });
        root.querySelectorAll('[data-step-dot]').forEach((dot) => {
            const n = Number(dot.dataset.stepDot);
            dot.classList.toggle('active', n <= Math.min(step, 5));
            dot.classList.toggle('current', n === step);
        });
        const progress = root.querySelector('.checkin-progress');
        progress?.setAttribute('aria-valuenow', String(Math.min(step, 5)));
        root.querySelector('.checkin-sheet')?.setAttribute('data-current-step', String(step));

        const copy = stepCopy[step];
        root.querySelector('#checkin-step-label').textContent = copy.label;
        const title = root.querySelector('#checkin-title');
        title.textContent = step < 6 ? copy.title : 'You’re checked in';
        if (stepHasRendered) title.focus({ preventScroll: true });
        stepHasRendered = true;

        backBtn.classList.toggle('hidden', step === 1 || step === 6);
        nextBtn.disabled = false;
        nextBtn.textContent = hasPrizeReceipt() && step === 6
            ? 'Keep this screen open'
            : copy.next;
        nextBtn.classList.toggle('hidden', hasPrizeReceipt() && step === 6);
        root.querySelector('.checkin-actions')?.classList.toggle('checkin-actions--done', hasPrizeReceipt() && step === 6);

        const sheet = root.querySelector('.checkin-sheet');
        if (sheet) {
            sheet.scrollTop = 0;
        }
        root.scrollTop = 0;

        if (step === 4) {
            prepareLocationStep();
        }
    }

    async function searchCompanies(query) {
        if (! companyResults) return;
        if (query.length < 1) {
            companySearchAbort?.abort();
            companyResults.hidden = true;
            companyResults.innerHTML = '';
            return;
        }

        companySearchAbort?.abort();
        companySearchAbort = new AbortController();
        const { signal } = companySearchAbort;

        companyResults.hidden = false;
        companyResults.innerHTML = '<li><small>Searching Malaysia companies…</small></li>';

        try {
            const data = await window.expoFetch(`/api/companies/search?q=${encodeURIComponent(query)}`, { signal });
            if (signal.aborted) return;
            const rows = data.results || [];
            const customItem = `
                <li class="company-suggest-custom">
                    <button type="button" data-custom="1">
                        <strong>Use “${escapeHtml(query)}”</strong>
                        <small>${rows.length ? 'Not in the list? That’s fine — place a pin next.' : 'Not listed — that’s perfectly fine. You’ll place a pin next.'}</small>
                    </button>
                </li>
            `;
            const matchItems = rows.map((row, index) => `
                <li>
                    <button type="button" data-company-index="${index}">
                        <strong>${escapeHtml(row.name)}</strong>
                        <small>${escapeHtml(row.location || 'Malaysia')}${row.has_location ? '' : ' · place on map next'}</small>
                    </button>
                </li>
            `).join('');

            // Custom name first so unlisted companies are always one tap away.
            companyResults.innerHTML = customItem + matchItems;

            companyResults.querySelectorAll('[data-company-index]').forEach((button) => {
                button.addEventListener('click', () => {
                    applyCompany(rows[Number(button.dataset.companyIndex)]);
                });
            });
            companyResults.querySelector('[data-custom]')?.addEventListener('click', () => {
                companyInput.value = query;
                selectedCompany = null;
                companyResults.hidden = true;
            });
        } catch (error) {
            if (error?.name === 'AbortError' || signal.aborted) return;
            companyResults.innerHTML = `
                <li class="company-suggest-custom">
                    <button type="button" data-custom="1">
                        <strong>Use “${escapeHtml(query)}”</strong>
                        <small>Search is quiet right now — typing your company still works.</small>
                    </button>
                </li>
            `;
            companyResults.querySelector('button')?.addEventListener('click', () => {
                companyInput.value = query;
                selectedCompany = null;
                companyResults.hidden = true;
            });
        }
    }

    function applyCompany(company) {
        selectedCompany = company;
        companyInput.value = company.name;
        form.querySelector('[name="city"]').value = company.city || '';
        form.querySelector('[name="region"]').value = company.region || '';
        form.querySelector('[name="country"]').value = company.country || 'Malaysia';
        form.querySelector('[name="address_text"]').value = company.location || company.name;
        if (company.website) {
            form.querySelector('[name="public_website"]').value = company.website;
        }
        companyResults.hidden = true;

        if (company.has_location && company.latitude != null && company.longitude != null) {
            form.querySelector('[name="latitude"]').value = Number(company.latitude).toFixed(7);
            form.querySelector('[name="longitude"]').value = Number(company.longitude).toFixed(7);
        } else {
            form.querySelector('[name="latitude"]').value = '';
            form.querySelector('[name="longitude"]').value = '';
        }
    }

    function prepareLocationStep() {
        ensureCheckinMap();
        const lat = form.querySelector('[name="latitude"]').value;
        const lng = form.querySelector('[name="longitude"]').value;
        const hasPin = lat && lng;

        if (hasPin) {
            setPin(Number(lng), Number(lat), { reverse: false, fly: true });
            if (locationBanner) {
                locationBanner.hidden = false;
                root.querySelector('#location-banner-title').textContent = selectedCompany?.has_location
                    ? 'Company location found'
                    : 'Location ready';
                root.querySelector('#location-banner-copy').textContent = selectedCompany?.location
                    || [form.city.value, form.region.value, form.country.value].filter(Boolean).join(', ')
                    || 'Looks good — adjust anytime on the map.';
            }
            if (locationHelp) {
                locationHelp.textContent = 'We found your company’s pin. Confirm it, or search or drag to move it.';
            }
        } else {
            if (locationBanner) locationBanner.hidden = true;
            if (locationHelp) {
                locationHelp.textContent = 'Search for your office or area, or tap the map to drop your pin.';
            }
        }
    }

    function ensureCheckinMap() {
        if (checkinMap) {
            checkinMap.resize();
            syncPinPreview();
            return;
        }
        if (! maplibregl) {
            showError('Map is taking a break — enter a city if you can, or try again in a moment.');
            return;
        }

        checkinMap = new maplibregl.Map({
            container: 'checkin-map',
            style: root.dataset.mapStyle,
            center: [101.9758, 4.2105],
            zoom: 5.4,
            attributionControl: false,
        });

        checkinMap.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        checkinMap.on('style.load', () => {
            try { checkinMap.setProjection({ type: 'mercator' }); } catch (_) {}
        });
        checkinMap.on('click', (event) => {
            if (locationBanner) locationBanner.hidden = true;
            setPin(event.lngLat.lng, event.lngLat.lat, { reverse: true, fly: false });
        });
        checkinMap.on('load', () => {
            const lat = form.querySelector('[name="latitude"]').value;
            const lng = form.querySelector('[name="longitude"]').value;
            if (lat && lng) setPin(Number(lng), Number(lat), { reverse: false, fly: true });
            syncPinPreview();
        });
    }

    function currentAvatarSrc() {
        if (uploadedObjectUrl) return uploadedObjectUrl;
        return avatarUrl(avatarSeedInput.value || form.full_name?.value || 'friend', 96);
    }

    function syncPinPreview() {
        if (! pinMarker) return;
        const img = pinMarker.getElement().querySelector('img');
        if (img) img.src = currentAvatarSrc();
    }

    function setPin(lng, lat, { reverse = false, fly = true } = {}) {
        const longitude = Number(lng);
        const latitude = Number(lat);
        if (! Number.isFinite(longitude) || ! Number.isFinite(latitude)) return;

        form.querySelector('[name="longitude"]').value = longitude.toFixed(7);
        form.querySelector('[name="latitude"]').value = latitude.toFixed(7);
        if (! maplibregl || ! checkinMap) return;

        if (! pinMarker) {
            const el = document.createElement('div');
            el.className = 'avatar-marker avatar-marker--pop';
            el.innerHTML = `
                <span class="avatar-marker-face">
                    <img src="${currentAvatarSrc()}" alt="" width="46" height="46">
                </span>
                <span class="avatar-marker-tip" aria-hidden="true"></span>
            `;
            pinMarker = new maplibregl.Marker({ element: el, anchor: 'bottom', draggable: true })
                .setLngLat([longitude, latitude])
                .addTo(checkinMap);

            pinMarker.on('dragend', () => {
                const pos = pinMarker.getLngLat();
                form.querySelector('[name="longitude"]').value = pos.lng.toFixed(7);
                form.querySelector('[name="latitude"]').value = pos.lat.toFixed(7);
                reverseGeocode(pos.lng, pos.lat);
            });
        } else {
            syncPinPreview();
            pinMarker.setLngLat([longitude, latitude]);
        }

        if (fly) {
            checkinMap.easeTo({
                center: [longitude, latitude],
                zoom: Math.max(checkinMap.getZoom(), 8.5),
                duration: 700,
            });
        }
        if (reverse) reverseGeocode(longitude, latitude);
    }

    function reverseGeocode(lng, lat) {
        clearTimeout(reverseTimer);
        reverseTimer = setTimeout(async () => {
            try {
                const data = await window.expoFetch(`/api/geocode/reverse?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);
                if (data.result) applyLocationFields(data.result);
            } catch (_) {}
        }, 220);
    }

    function setLocationStatus(message, tone = '') {
        if (! locationStatus) return;
        locationStatus.textContent = message;
        locationStatus.dataset.tone = tone;
        locationStatus.hidden = ! message;
    }

    function setLocationActive(index) {
        locationActive = index;
        locationResults.querySelectorAll('[data-result-index]').forEach((el, i) => {
            const on = i === index;
            el.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) {
                locationQuery?.setAttribute('aria-activedescendant', el.id);
                el.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function renderLocationRows(rows) {
        locationRows = rows;
        locationActive = -1;
        locationQuery?.removeAttribute('aria-activedescendant');
        locationQuery?.setAttribute('aria-expanded', rows.length ? 'true' : 'false');
        locationResults.hidden = ! rows.length;
        locationResults.innerHTML = rows.map((result, index) => {
            const name = result.name || String(result.label || '').split(',')[0];
            const rest = String(result.label || '').split(',').slice(1).join(',').trim();
            return `
                <li role="option" id="location-option-${index}" data-result-index="${index}" aria-selected="false" tabindex="-1">
                    <span class="location-option-icon" aria-hidden="true"></span>
                    <span class="location-option-text">
                        <strong>${escapeHtml(name)}</strong>
                        ${rest ? `<small>${escapeHtml(rest)}</small>` : ''}
                    </span>
                </li>`;
        }).join('');
    }

    function pickLocation(index) {
        const result = locationRows[index];
        if (! result) return;
        applyLocationFields(result);
        setPin(result.longitude, result.latitude, { reverse: false, fly: false });
        checkinMap?.easeTo({ center: [Number(result.longitude), Number(result.latitude)], zoom: 15, duration: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 700 });
        if (locationBanner) locationBanner.hidden = true;
        renderLocationRows([]);
        if (locationQuery) locationQuery.value = result.name || String(result.label || '').split(',')[0];
        setLocationStatus(`Pin placed at ${result.label}. Drag your avatar to fine-tune.`, 'ok');
        hideError();
        checkinMap?.getContainer().scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    async function searchLocation({ force = false } = {}) {
        const q = locationQuery?.value.trim() || '';
        if (q.length < 2) {
            setLocationStatus('Type a place, area or address — or tap the map.', 'warn');
            locationQuery?.focus();
            return;
        }
        if (! force && q === lastLocationQuery && locationRows.length) return;
        lastLocationQuery = q;

        locationAbort?.abort();
        locationAbort = new AbortController();
        const { signal } = locationAbort;
        const wrap = root.querySelector('#location-search');
        wrap?.setAttribute('aria-busy', 'true');
        setLocationStatus('Searching Malaysia…', 'busy');

        try {
            const data = await window.expoFetch(`/api/geocode?q=${encodeURIComponent(q)}`, { signal });
            if (signal.aborted) return;
            const rows = (data.results || []).filter((row) => Number.isFinite(Number(row.latitude)) && Number.isFinite(Number(row.longitude)));
            renderLocationRows(rows);
            setLocationStatus(rows.length
                ? `${rows.length} ${rows.length === 1 ? 'place' : 'places'} found — tap one to drop your pin.`
                : `No places match “${q}”. Try a city or area name, or tap the map.`, rows.length ? '' : 'warn');
        } catch (error) {
            if (signal.aborted || error?.name === 'AbortError') return;
            renderLocationRows([]);
            // Keep what they typed so the address still reaches staff.
            form.querySelector('[name="address_text"]').value = q;
            setLocationStatus('Place search is unavailable right now. Tap the map or drag your avatar to your area — we’ll keep the address you typed.', 'error');
        } finally {
            if (! signal.aborted) wrap?.removeAttribute('aria-busy');
        }
    }

    function applyLocationFields(result) {
        form.querySelector('[name="city"]').value = result.city || form.querySelector('[name="city"]').value || '';
        form.querySelector('[name="region"]').value = result.region || form.querySelector('[name="region"]').value || '';
        form.querySelector('[name="country"]').value = result.country || form.querySelector('[name="country"]').value || 'Malaysia';
        form.querySelector('[name="address_text"]').value = result.address_text || result.label || '';
    }

    function validateStep(current) {
        if (current === 1) {
            if (! form.full_name.value.trim()) {
                showError('Add your name so the booth team can greet you.', form.full_name);
                return false;
            }
            if (! form.email.value.trim() && ! form.phone.value.trim()) {
                showError('Add an email or a phone number — one is enough.', [form.email, form.phone]);
                return false;
            }
            if (form.email.value.trim() && ! form.email.checkValidity()) {
                showError('That email looks incomplete — check for a typo, e.g. name@company.my.', form.email);
                return false;
            }
            return true;
        }
        if (current === 2) {
            if (! form.company_name.value.trim()) {
                showError('Add your company — pick one from the list or type it in.', form.company_name);
                return false;
            }
            return true;
        }
        if (current === 3) {
            if (! form.querySelectorAll('input[name="services[]"]:checked').length) {
                showError('Choose at least one topic you’d like to hear about.');
                return false;
            }
            return true;
        }
        if (current === 4) {
            if (! form.latitude.value || ! form.longitude.value) {
                showError('Search for a place or tap the map to drop your pin.', locationQuery);
                return false;
            }
            return true;
        }
        if (current === 5) {
            if (! form.privacy_accepted.checked) {
                showError('Please confirm you’ve read the privacy note to continue.', form.privacy_accepted);
                return false;
            }
            return true;
        }
        return true;
    }

    async function submit() {
        if (submitting) return;
        submitting = true;
        nextBtn.disabled = true;
        nextBtn.textContent = 'Joining…';

        const serviceIds = [...form.querySelectorAll('input[name="services[]"]:checked')].map((el) => el.value);
        const body = new FormData();
        body.append('full_name', form.full_name.value.trim());
        body.append('job_title', form.job_title.value.trim());
        body.append('email', form.email.value.trim());
        body.append('phone', form.phone.value.trim());
        body.append('company_name', form.company_name.value.trim());
        body.append('address_text', form.address_text.value.trim());
        body.append('city', form.city.value.trim());
        body.append('region', form.region.value.trim());
        body.append('country', form.country.value.trim() || 'Malaysia');
        body.append('latitude', form.latitude.value);
        body.append('longitude', form.longitude.value);
        body.append('public_website', form.public_website.value.trim());
        body.append('display_message', form.display_message?.value.trim() || '');
        body.append('public_display_enabled', form.public_display_enabled.checked ? '1' : '0');
        body.append('follow_up_consent', form.follow_up_consent.checked ? '1' : '0');
        body.append('privacy_accepted', form.privacy_accepted.checked ? '1' : '0');
        body.append('services', serviceIds.join(','));
        if (uploadedFile) {
            body.append('avatar', uploadedFile);
        } else {
            body.append('avatar_seed', avatarSeedInput.value || form.full_name.value.trim() || 'friend');
        }

        try {
            const result = await window.expoFetch('/api/check-in', {
                method: 'POST',
                body,
            });

            const successAvatar = root.querySelector('#success-avatar');
            if (successAvatar) {
                successAvatar.src = result.avatar_url
                    || result.company?.properties?.avatar_url
                    || currentAvatarSrc();
            }

            if (hasPrizeReceipt()) {
                root.querySelector('#success-title').textContent = 'You’re in!';
                root.querySelector('#success-copy').textContent = 'Keep this receipt open and show it at the counter to claim your prize.';
                const claimCode = root.querySelector('#prize-claim-code');
                const claimName = root.querySelector('#prize-claim-name');
                const claimCompany = root.querySelector('#prize-claim-company');
                if (claimCode) claimCode.textContent = result.claim_code || 'CK-————';
                if (claimName) claimName.textContent = result.full_name || form.full_name.value.trim() || '—';
                if (claimCompany) {
                    claimCompany.textContent = result.company_name
                        || result.company?.properties?.name
                        || form.company_name.value.trim()
                        || '—';
                }
            } else {
                root.querySelector('#success-title').textContent = 'Looking good!';
                root.querySelector('#success-copy').textContent = result.public_display_enabled
                    ? 'Your avatar is popping onto the Malaysia map.'
                    : 'You’re checked in privately — still glad you came!';
            }

            goTo(6);
            await onSuccess?.(result);
        } catch (error) {
            const messages = error.payload?.errors
                ? Object.values(error.payload.errors).flat().join(' ')
                : error.message;
            showError(messages || 'That didn’t go through — try once more.');
            nextBtn.disabled = false;
            nextBtn.textContent = stepCopy[5].next;
            submitting = false;
        }
    }

    function showError(message, field = null) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
        form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
        const fields = (Array.isArray(field) ? field : [field]).filter(Boolean);
        fields.forEach((el) => {
            el.setAttribute('aria-invalid', 'true');
            el.setAttribute('aria-describedby', 'checkin-error');
        });
        if (fields[0]) {
            fields[0].focus({ preventScroll: true });
            fields[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        } else {
            errorEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function hideError() {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
    }

    form.addEventListener('input', (event) => {
        if (event.target.getAttribute?.('aria-invalid') === 'true') {
            event.target.removeAttribute('aria-invalid');
            if (! form.querySelector('[aria-invalid="true"]')) hideError();
        }
    });

    // Keep the focused field visible above the on-screen keyboard.
    form.addEventListener('focusin', (event) => {
        const el = event.target;
        if (! el.matches?.('input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="search"]')) return;
        setTimeout(() => el.scrollIntoView({ block: 'center', behavior: 'smooth' }), 260);
    });

    return { open, close };
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
