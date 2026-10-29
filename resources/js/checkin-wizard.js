import { avatarUrl } from './avatars';

export function createCheckInWizard({ root, maplibregl, services, onSuccess, lockOpen = false }) {
    const modal = root.querySelector('#checkin-modal');
    const form = root.querySelector('#checkin-form');
    const errorEl = root.querySelector('#checkin-error');
    const nextBtn = root.querySelector('#checkin-next');
    const backBtn = root.querySelector('#checkin-back');
    const interestOptions = root.querySelector('#interest-options');
    const locationResults = root.querySelector('#location-results');
    const companyInput = root.querySelector('#company-name-input') || form.querySelector('[name="company_name"]');
    const companyResults = root.querySelector('#company-results');
    let step = 1;
    let checkinMap = null;
    let pinMarker = null;
    let submitting = false;
    let knownCompanies = [];
    let companySearchTimer = null;
    let reverseTimer = null;

    interestOptions.innerHTML = services.map((service) => `
        <label class="interest-option" style="--interest-color:${service.color || '#007aff'}">
            <input type="checkbox" name="services[]" value="${service.id}">
            <span style="width:.7rem;height:.7rem;border-radius:999px;background:${service.color || '#007aff'};flex:0 0 auto"></span>
            <span>${service.name}</span>
        </label>
    `).join('');

    loadKnownCompanies().catch(() => {});

    root.querySelector('#checkin-close')?.addEventListener('click', close);
    root.querySelector('#location-search-btn').addEventListener('click', searchLocation);
    root.querySelector('#location-query').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchLocation();
        }
    });

    companyInput?.addEventListener('input', () => {
        clearTimeout(companySearchTimer);
        companySearchTimer = setTimeout(() => renderCompanySuggestions(companyInput.value.trim()), 180);
    });

    companyInput?.addEventListener('focus', () => {
        renderCompanySuggestions(companyInput.value.trim());
    });

    document.addEventListener('click', (event) => {
        if (! companyResults) {
            return;
        }
        if (event.target === companyInput || companyResults.contains(event.target)) {
            return;
        }
        companyResults.hidden = true;
    });

    nextBtn.addEventListener('click', async () => {
        hideError();
        if (step < 4) {
            if (!validateStep(step)) return;
            goTo(step + 1);
            return;
        }

        if (step === 4) {
            if (!validateStep(4)) return;
            await submit();
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
        if (step > 1 && step < 5) goTo(step - 1);
    });

    function open() {
        if (step !== 5) {
            resetForm();
        }
        modal.classList.remove('hidden');
        goTo(step === 5 ? 5 : 1);
    }

    function close() {
        if (lockOpen && step === 5) {
            return;
        }
        modal.classList.add('hidden');
        if (checkinMap) {
            checkinMap.remove();
            checkinMap = null;
            pinMarker = null;
        }
    }

    function resetForm() {
        form.reset();
        locationResults.innerHTML = '';
        if (companyResults) {
            companyResults.innerHTML = '';
            companyResults.hidden = true;
        }
        hideError();
        submitting = false;
        nextBtn.disabled = false;
        form.querySelector('[name="latitude"]').value = '';
        form.querySelector('[name="longitude"]').value = '';
        if (pinMarker) {
            pinMarker.remove();
            pinMarker = null;
        }
    }

    const stepCopy = {
        1: { label: '1 of 4', title: 'Create your pin', next: 'Continue' },
        2: { label: '2 of 4', title: 'Pick your interests', next: 'Continue' },
        3: { label: '3 of 4', title: 'Place your avatar', next: 'Continue' },
        4: { label: '4 of 4', title: 'Almost done', next: 'Add me to the globe' },
        5: { label: 'Done', title: 'Looking good!', next: 'Back to the map' },
    };

    function goTo(nextStep) {
        step = nextStep;
        form.querySelectorAll('[data-step]').forEach((section) => {
            section.classList.toggle('hidden', Number(section.dataset.step) !== step);
        });
        root.querySelectorAll('[data-step-dot]').forEach((dot) => {
            dot.classList.toggle('active', Number(dot.dataset.stepDot) <= step);
        });

        const copy = stepCopy[step];
        root.querySelector('#checkin-step-label').textContent = copy.label;
        if (step < 5) {
            root.querySelector('#checkin-title').textContent = copy.title;
        }

        backBtn.classList.toggle('hidden', step === 1 || step === 5);
        nextBtn.disabled = false;
        nextBtn.textContent = copy.next;

        if (step === 3) {
            ensureCheckinMap();
            maybePlaceFromCompanyName();
        }
    }

    async function loadKnownCompanies() {
        const data = await window.expoFetch('/api/map/companies');
        knownCompanies = (data.features || []).map((feature) => {
            const props = feature.properties || {};
            const [lng, lat] = feature.geometry?.coordinates || [];
            return {
                id: props.id,
                name: props.name,
                location: props.location,
                city: props.city,
                region: props.region,
                country: props.country,
                latitude: lat,
                longitude: lng,
            };
        }).filter((item) => item.name && item.latitude != null && item.longitude != null);
    }

    function renderCompanySuggestions(query) {
        if (! companyResults) {
            return;
        }

        const needle = query.toLowerCase();
        const matches = knownCompanies
            .filter((company) => !needle || company.name.toLowerCase().includes(needle))
            .slice(0, 6);

        if (! matches.length) {
            companyResults.hidden = true;
            companyResults.innerHTML = '';
            return;
        }

        companyResults.hidden = false;
        companyResults.innerHTML = matches.map((company, index) => `
            <li>
                <button type="button" data-company-index="${index}">
                    <strong>${escapeHtml(company.name)}</strong>
                    <small>${escapeHtml(company.location || 'Malaysia')}</small>
                </button>
            </li>
        `).join('');

        companyResults._matches = matches;
        companyResults.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => {
                const company = matches[Number(button.dataset.companyIndex)];
                selectCompany(company);
            });
        });
    }

    function selectCompany(company) {
        companyInput.value = company.name;
        form.querySelector('[name="city"]').value = company.city || '';
        form.querySelector('[name="region"]').value = company.region || '';
        form.querySelector('[name="country"]').value = company.country || 'Malaysia';
        form.querySelector('[name="address_text"]').value = company.location || company.name;
        if (companyResults) {
            companyResults.hidden = true;
        }
        setPin(company.longitude, company.latitude, { reverse: false, fly: true });
    }

    function maybePlaceFromCompanyName() {
        const name = companyInput?.value.trim().toLowerCase();
        if (! name || form.latitude.value) {
            return;
        }
        const match = knownCompanies.find((company) => company.name.toLowerCase() === name);
        if (match) {
            selectCompany(match);
        }
    }

    function ensureCheckinMap() {
        if (checkinMap) {
            checkinMap.resize();
            return;
        }

        if (! maplibregl) {
            showError('Map is unavailable. Enter city/region/country and skip pin placement if needed.');
            return;
        }

        // Flat map for accurate pin tapping — globe stays on the big booth display.
        checkinMap = new maplibregl.Map({
            container: 'checkin-map',
            style: root.dataset.mapStyle,
            center: [101.9758, 4.2105],
            zoom: 5.4,
            attributionControl: false,
        });

        checkinMap.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        checkinMap.on('style.load', () => {
            try {
                checkinMap.setProjection({ type: 'mercator' });
            } catch (_) {
                // Older builds without projection API can ignore this.
            }
        });

        checkinMap.on('click', (event) => {
            setPin(event.lngLat.lng, event.lngLat.lat, { reverse: true, fly: false });
        });
    }

    function setPin(lng, lat, { reverse = false, fly = true } = {}) {
        const longitude = Number(lng);
        const latitude = Number(lat);
        if (! Number.isFinite(longitude) || ! Number.isFinite(latitude)) {
            return;
        }

        form.querySelector('[name="longitude"]').value = longitude.toFixed(7);
        form.querySelector('[name="latitude"]').value = latitude.toFixed(7);

        if (! maplibregl || ! checkinMap) {
            return;
        }

        if (! pinMarker) {
            const seed = form.company_name?.value || form.full_name?.value || 'friend';
            const el = document.createElement('div');
            el.className = 'avatar-marker avatar-marker--pop';
            el.innerHTML = `
                <span class="avatar-marker-inner">
                    <span class="avatar-marker-face">
                        <img src="${avatarUrl(seed, 96)}" alt="" width="46" height="46">
                    </span>
                </span>
            `;
            pinMarker = new maplibregl.Marker({
                element: el,
                anchor: 'bottom',
                draggable: true,
                pitchAlignment: 'viewport',
                rotationAlignment: 'viewport',
            })
                .setLngLat([longitude, latitude])
                .addTo(checkinMap);

            pinMarker.on('dragend', () => {
                const pos = pinMarker.getLngLat();
                form.querySelector('[name="longitude"]').value = pos.lng.toFixed(7);
                form.querySelector('[name="latitude"]').value = pos.lat.toFixed(7);
                reverseGeocode(pos.lng, pos.lat);
            });
        } else {
            const seed = form.company_name?.value || form.full_name?.value || 'friend';
            const img = pinMarker.getElement().querySelector('img');
            if (img) {
                img.src = avatarUrl(seed, 96);
            }
            pinMarker.setLngLat([longitude, latitude]);
        }

        if (fly) {
            checkinMap.easeTo({
                center: [longitude, latitude],
                zoom: Math.max(checkinMap.getZoom(), 8.5),
                duration: 700,
            });
        }

        if (reverse) {
            reverseGeocode(longitude, latitude);
        }
    }

    function reverseGeocode(lng, lat) {
        clearTimeout(reverseTimer);
        reverseTimer = setTimeout(async () => {
            try {
                const data = await window.expoFetch(`/api/geocode/reverse?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);
                if (! data.result) {
                    return;
                }
                applyLocationFields(data.result, { keepPin: true });
            } catch (_) {
                // Pin is already saved even if reverse lookup fails.
            }
        }, 220);
    }

    async function searchLocation() {
        const q = root.querySelector('#location-query').value.trim();
        if (q.length < 2) {
            showError('Search a company or place, or tap the map.');
            return;
        }

        hideError();
        locationResults.innerHTML = '<li><small>Searching…</small></li>';

        const companyMatches = knownCompanies
            .filter((company) => company.name.toLowerCase().includes(q.toLowerCase()))
            .slice(0, 4)
            .map((company) => ({
                type: 'company',
                label: company.name,
                company,
            }));

        try {
            const data = await window.expoFetch(`/api/geocode?q=${encodeURIComponent(q)}`);
            const placeMatches = (data.results || []).map((result) => ({
                type: 'place',
                label: result.label,
                result,
            }));

            const rows = [...companyMatches, ...placeMatches];
            if (! rows.length) {
                locationResults.innerHTML = '<li><small>No matches. Tap the map to place your avatar.</small></li>';
                return;
            }

            locationResults.innerHTML = rows.map((row, index) => `
                <li>
                    <button type="button" data-result-index="${index}">
                        <strong>${escapeHtml(row.type === 'company' ? row.company.name : 'Place')}</strong>
                        <small>${escapeHtml(row.type === 'company' ? (row.company.location || 'On the map') : row.label)}</small>
                    </button>
                </li>
            `).join('');

            locationResults.querySelectorAll('button').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = rows[Number(button.dataset.resultIndex)];
                    if (row.type === 'company') {
                        selectCompany(row.company);
                        locationResults.innerHTML = `<li><small>Pinned to ${escapeHtml(row.company.name)}</small></li>`;
                        return;
                    }
                    applyLocation(row.result);
                });
            });
        } catch (error) {
            if (companyMatches.length) {
                locationResults.innerHTML = companyMatches.map((row, index) => `
                    <li>
                        <button type="button" data-company-only="${index}">
                            <strong>${escapeHtml(row.company.name)}</strong>
                            <small>${escapeHtml(row.company.location || 'On the map')}</small>
                        </button>
                    </li>
                `).join('');
                locationResults.querySelectorAll('[data-company-only]').forEach((button) => {
                    button.addEventListener('click', () => {
                        selectCompany(companyMatches[Number(button.dataset.companyOnly)].company);
                    });
                });
                return;
            }
            locationResults.innerHTML = '';
            showError(error.message || 'Location search failed. Tap the map to place your avatar.');
        }
    }

    function applyLocation(result) {
        applyLocationFields(result, { keepPin: false });
        setPin(result.longitude, result.latitude, { reverse: false, fly: true });
        locationResults.innerHTML = `<li><small>Pin set: ${escapeHtml(result.label)}</small></li>`;
    }

    function applyLocationFields(result, { keepPin = false } = {}) {
        form.querySelector('[name="city"]').value = result.city || form.querySelector('[name="city"]').value || '';
        form.querySelector('[name="region"]').value = result.region || form.querySelector('[name="region"]').value || '';
        form.querySelector('[name="country"]').value = result.country || form.querySelector('[name="country"]').value || 'Malaysia';
        form.querySelector('[name="address_text"]').value = result.address_text || result.label || '';
        if (! keepPin && result.longitude != null && result.latitude != null) {
            // Caller may place the pin separately.
        }
    }

    function validateStep(current) {
        if (current === 1) {
            const fullName = form.full_name.value.trim();
            const company = form.company_name.value.trim();
            const email = form.email.value.trim();
            const phone = form.phone.value.trim();
            if (!fullName || !company) {
                showError('Please enter your name and company.');
                return false;
            }
            if (!email && !phone) {
                showError('Provide an email or phone number.');
                return false;
            }
            return true;
        }

        if (current === 2) {
            const selected = form.querySelectorAll('input[name="services[]"]:checked');
            if (!selected.length) {
                showError('Select at least one interest.');
                return false;
            }
            return true;
        }

        if (current === 3) {
            if (!form.latitude.value || !form.longitude.value) {
                showError('Search for a company/place or tap the map to place your avatar.');
                return false;
            }
            return true;
        }

        if (current === 4) {
            if (!form.privacy_accepted.checked) {
                showError('Please confirm the privacy notice.');
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
        nextBtn.textContent = 'Submitting…';

        const serviceIds = [...form.querySelectorAll('input[name="services[]"]:checked')].map((el) => Number(el.value));

        const payload = {
            full_name: form.full_name.value.trim(),
            job_title: form.job_title.value.trim() || null,
            email: form.email.value.trim() || null,
            phone: form.phone.value.trim() || null,
            company_name: form.company_name.value.trim(),
            address_text: form.address_text.value.trim() || null,
            city: form.city.value.trim() || null,
            region: form.region.value.trim() || null,
            country: form.country.value.trim() || null,
            latitude: Number(form.latitude.value),
            longitude: Number(form.longitude.value),
            public_website: form.public_website.value.trim() || null,
            display_message: form.display_message?.value.trim() || null,
            public_display_enabled: form.public_display_enabled.checked,
            follow_up_consent: form.follow_up_consent.checked,
            privacy_accepted: form.privacy_accepted.checked,
            services: serviceIds,
        };

        try {
            const result = await window.expoFetch('/api/check-in', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            const successTitle = root.querySelector('#success-title');
            const successCopy = root.querySelector('#success-copy');
            const successAvatar = root.querySelector('#success-avatar');
            if (successAvatar) {
                const seed = result.company?.properties?.name || form.full_name.value || 'friend';
                successAvatar.src = avatarUrl(seed, 160);
                successAvatar.alt = seed;
            }

            successTitle.textContent = 'Looking good!';
            successCopy.textContent = result.public_display_enabled
                ? 'Your people avatar is popping onto the Malaysia globe.'
                : 'You’re checked in. Your company stays private — still glad you came!';

            goTo(5);
            await onSuccess?.(result);
        } catch (error) {
            const messages = error.payload?.errors
                ? Object.values(error.payload.errors).flat().join(' ')
                : error.message;
            showError(messages || 'That didn’t go through — try once more.');
            nextBtn.disabled = false;
            nextBtn.textContent = stepCopy[4].next;
            submitting = false;
        }
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function hideError() {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
    }

    return { open, close };
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
