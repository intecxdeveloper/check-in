import { createCheckInWizard } from './checkin-wizard';
import { avatarUrl, resolveAvatarUrl } from './avatars';

export function createExpoMap(root, maplibregl) {
    const mapStyle = root.dataset.mapStyle;
    const services = JSON.parse(root.dataset.services || '[]');
    const checkInUrl = root.dataset.checkInUrl || '/check-in';
    const knownIds = new Set();
    const knownVisitorIds = new Set();
    const markersById = new Map();
    let activeService = null;
    let lastRegionCount = 0;
    let mapReady = false;
    let map = null;
    let recentBootstrapped = false;
    let toastTimer = null;

    const statsEls = {
        check_ins: root.querySelector('[data-stat="check_ins"]'),
        companies: root.querySelector('[data-stat="companies"]'),
        regions: root.querySelector('[data-stat="regions"]'),
    };

    const filtersEl = root.querySelector('#service-filters');
    const recentList = root.querySelector('#recent-list');
    const companyCard = root.querySelector('#company-card');
    const milestone = root.querySelector('#milestone');
    const toastEl = root.querySelector('#checkin-toast');

    renderCheckInQr(checkInUrl);

    if (filtersEl && ! filtersEl.hidden) {
        filtersEl.innerHTML = [
            chipButton('All', null, true),
            ...services.map((service) => chipButton(service.name, service.slug, false, service.color)),
        ].join('');

        filtersEl.addEventListener('click', (event) => {
            const button = event.target.closest('[data-service]');
            if (! button) {
                return;
            }

            activeService = button.dataset.service || null;
            filtersEl.querySelectorAll('.expo-chip').forEach((el) => {
                const on = el === button;
                el.classList.toggle('active', on);
                if (on) {
                    const service = services.find((item) => item.slug === el.dataset.service);
                    el.style.background = service?.color || '#007aff';
                    el.style.color = '#fff';
                } else {
                    el.style.background = '';
                    el.style.color = '';
                }
            });
            refreshCompanies({ animateNew: false });
        });
    }

    root.querySelector('#company-card-close')?.addEventListener('click', closeCompanyCard);

    refreshStats().catch((error) => console.error(error));
    refreshRecent({ announce: false }).catch((error) => console.error(error));
    setInterval(() => {
        refreshStats().catch((error) => console.error(error));
        refreshRecent({ announce: true }).catch((error) => console.error(error));
        if (mapReady) {
            refreshCompanies({ animateNew: true }).catch((error) => console.error(error));
        }
    }, 2000);

    if (maplibregl) {
        initMap();
    } else {
        showMapFallback('Map library failed to load. Counters still update live.');
    }

    const wizard = createCheckInWizard({
        root,
        maplibregl,
        services,
        onSuccess: async (payload) => {
            const companyName = payload.company?.properties?.name || 'A new friend';
            showCheckInToast({
                first_name: payload.first_name,
                company: companyName,
                message: payload.display_message || 'Welcome to the map!',
                location: payload.company?.properties?.location || 'Malaysia',
                avatar_url: payload.avatar_url || payload.company?.properties?.avatar_url,
            });

            await Promise.all([refreshStats(), refreshRecent({ announce: false })]);

            if (payload.company && mapReady) {
                if (payload.display_message) {
                    payload.company.properties.message = payload.display_message;
                }
                await refreshCompanies({ animateNew: true, highlightId: payload.company.properties.id });
                const [lng, lat] = payload.company.geometry.coordinates;
                map.flyTo({
                    center: [lng, lat],
                    zoom: Math.max(map.getZoom(), 5.4),
                    pitch: 10,
                    duration: 1800,
                    essential: true,
                });
            }
        },
    });

    root.querySelector('#checkin-open')?.addEventListener('click', () => wizard.open());

    window.__expoCelebrate = (item = {}) => showCheckInToast({
        first_name: item.first_name || 'Adam',
        company: item.company || 'Bright Maps Sdn Bhd',
        message: item.message || 'Hello from the booth!',
        location: item.location || 'Kuala Lumpur, Malaysia',
    });

    if (new URLSearchParams(window.location.search).get('demo') === 'join') {
        setTimeout(() => window.__expoCelebrate(), 900);
    }

    function initMap() {
        // Mercator + projected DOM pins keep avatars locked to real lat/lng.
        // Globe projection was drifting markers away from cities.
        const malaysiaBounds = [
            [99.4, 0.85],
            [119.4, 7.55],
        ];

        map = new maplibregl.Map({
            container: 'map',
            style: mapStyle,
            bounds: malaysiaBounds,
            fitBoundsOptions: { padding: 48 },
            pitch: 0,
            maxPitch: 0,
            attributionControl: false,
            failIfMajorPerformanceCaveat: false,
        });

        map.on('error', (event) => {
            console.error('MapLibre error', event?.error || event);
        });

        map.on('style.load', () => {
            try {
                map.setProjection({ type: 'mercator' });
            } catch (_) {
                // Ignore if projection API is unavailable.
            }
        });

        ensureMarkerLayer();
        map.on('render', syncMarkerPositions);
        map.on('move', syncMarkerPositions);
        map.on('zoom', syncMarkerPositions);
        map.on('resize', syncMarkerPositions);

        const loadTimeout = setTimeout(() => {
            if (! mapReady) {
                showMapFallback('Map tiles are slow to load. Showing counters while we keep trying…');
            }
        }, 8000);

        map.on('load', async () => {
            clearTimeout(loadTimeout);
            hideMapFallback();

            map.fitBounds(malaysiaBounds, { padding: 48, duration: 0 });
            mapReady = true;
            window.__expoMap = map;
            await refreshCompanies({ animateNew: false });
            syncMarkerPositions();
        });
    }

    function ensureMarkerLayer() {
        if (! map || root.querySelector('#avatar-marker-layer')) {
            return root.querySelector('#avatar-marker-layer');
        }

        const layer = document.createElement('div');
        layer.id = 'avatar-marker-layer';
        layer.className = 'avatar-marker-layer';
        map.getCanvasContainer().appendChild(layer);
        return layer;
    }

    function syncMarkerPositions() {
        if (! map) {
            return;
        }

        for (const entry of markersById.values()) {
            const point = map.project(entry.lngLat);
            const width = map.getContainer().clientWidth;
            const height = map.getContainer().clientHeight;
            const visible = Number.isFinite(point.x) && Number.isFinite(point.y)
                && point.x > -120 && point.y > -120
                && point.x < width + 120
                && point.y < height + 120;

            entry.el.style.opacity = visible ? '1' : '0';
            entry.el.style.pointerEvents = visible ? 'auto' : 'none';
            entry.el.style.transform = `translate3d(${point.x}px, ${point.y}px, 0) translate(-50%, -100%)`;
        }
    }

    async function refreshCompanies({ animateNew = true, highlightId = null } = {}) {
        if (! mapReady || ! map) {
            return;
        }

        const params = new URLSearchParams();
        if (activeService) {
            params.set('service', activeService);
        }

        const data = await window.expoFetch(`/api/map/companies?${params.toString()}`);
        const incoming = data.features || [];
        const nextIds = new Set(incoming.map((feature) => String(feature.properties.id)));
        const layer = ensureMarkerLayer();

        for (const [id, entry] of markersById.entries()) {
            if (! nextIds.has(id)) {
                entry.el.remove();
                markersById.delete(id);
            }
        }

        for (const feature of incoming) {
            const id = String(feature.properties.id);
            const props = normalizeProps(feature.properties);
            const [lng, lat] = feature.geometry.coordinates.map(Number);
            const lngLat = { lng, lat };
            const isNew = animateNew && ! knownIds.has(Number(id)) && ! knownIds.has(id);
            const shouldPop = isNew || Number(id) === Number(highlightId);

            if (markersById.has(id)) {
                const entry = markersById.get(id);
                entry.lngLat = lngLat;
                entry.props = props;
                updatePeopleMarker(entry.el, props, shouldPop);
            } else {
                const el = createPeopleMarker(props, shouldPop);
                layer.appendChild(el);
                markersById.set(id, { el, lngLat, props });
            }

            knownIds.add(Number(id));
            knownIds.add(id);
        }

        syncMarkerPositions();

        if (highlightId) {
            const feature = incoming.find((f) => Number(f.properties.id) === Number(highlightId));
            if (feature) {
                openCompanyCard(normalizeProps(feature.properties));
                markersById.get(String(highlightId))?.el.classList.add('avatar-marker--pop');
            }
        }
    }

    function createPeopleMarker(props, pop = false) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `avatar-marker${pop ? ' avatar-marker--pop' : ''}`;
        updatePeopleMarker(el, props, pop);
        el.addEventListener('click', (event) => {
            event.stopPropagation();
            openCompanyCard(el._props || props);
        });
        return el;
    }

    function updatePeopleMarker(el, props, pop = false) {
        const seed = String(props.name || props.id || 'friend');
        const message = String(props.message || '').trim();
        const imageUrl = resolveAvatarUrl({
            avatarUrl: props.avatar_url,
            seed,
            name: props.name,
            size: 96,
        });
        el._props = props;
        el.setAttribute('aria-label', props.name || 'Company');
        el.classList.toggle('avatar-marker--has-bubble', Boolean(message));

        if (pop) {
            el.classList.remove('avatar-marker--pop');
            void el.offsetWidth;
            el.classList.add('avatar-marker--pop');
        }

        if (el.dataset.seed === seed && el.dataset.message === message && el.dataset.image === imageUrl) {
            return;
        }

        el.dataset.seed = seed;
        el.dataset.message = message;
        el.dataset.image = imageUrl;
        el.innerHTML = `
            ${message ? `<span class="avatar-marker-bubble">${escapeHtml(message)}</span>` : ''}
            <span class="avatar-marker-face">
                <img src="${imageUrl}" alt="" width="46" height="46" loading="lazy">
            </span>
            <span class="avatar-marker-tip" aria-hidden="true"></span>
        `;
    }

    async function refreshStats() {
        const stats = await window.expoFetch('/api/map/stats');
        animateNumber(statsEls.check_ins, stats.check_ins);
        animateNumber(statsEls.companies, stats.companies);
        animateNumber(statsEls.regions, stats.regions);

        if (stats.regions >= 8 && lastRegionCount < 8) {
            showMilestone('Malaysia is glowing — 8 places joined!');
        }
        lastRegionCount = stats.regions;
    }

    async function refreshRecent({ announce = true } = {}) {
        if (! recentList) {
            return;
        }

        const data = await window.expoFetch('/api/map/recent');
        const items = data.items || [];

        if (! recentBootstrapped) {
            items.forEach((item) => knownVisitorIds.add(Number(item.id)));
            recentBootstrapped = true;
        } else if (announce) {
            const newcomers = items
                .filter((item) => ! knownVisitorIds.has(Number(item.id)))
                .sort((a, b) => Number(a.id) - Number(b.id));

            for (const item of newcomers) {
                knownVisitorIds.add(Number(item.id));
                showCheckInToast(item);

                if (item.public && item.longitude != null && item.latitude != null && mapReady && map) {
                    map.flyTo({
                        center: [Number(item.longitude), Number(item.latitude)],
                        zoom: Math.max(map.getZoom(), 5.2),
                        pitch: 8,
                        duration: 1600,
                        essential: true,
                    });
                    if (item.company_id) {
                        await refreshCompanies({ animateNew: true, highlightId: item.company_id });
                    }
                }
            }
        } else {
            items.forEach((item) => knownVisitorIds.add(Number(item.id)));
        }

        if (! recentList.hidden) {
            recentList.innerHTML = items.slice(0, 8).map((item) => {
                const seed = item.company || 'friend';
                const message = String(item.message || '').trim();
                return `
                    <li class="${message ? 'has-message' : ''}">
                        <img class="mini-avatar" src="${avatarUrl(seed, 72)}" alt="" width="36" height="36" loading="lazy">
                        <strong>${escapeHtml(item.company || 'A new friend')}</strong>
                        ${message ? `<em class="ticker-message">${escapeHtml(message)}</em>` : ''}
                        <small>${escapeHtml(item.location || 'Malaysia')} · ${escapeHtml(item.at || '')}${item.public ? '' : ' · private'}</small>
                    </li>
                `;
            }).join('') || '<li><small>Scan the QR to be first…</small></li>';
        }
    }

    function showCheckInToast(item) {
        if (! toastEl) {
            return;
        }

        const seed = item.company || item.first_name || 'friend';
        const name = String(item.first_name || item.company || 'A new friend').trim();
        const message = String(item.message || '').trim();
        const location = String(item.location || 'Malaysia').trim();
        const imageUrl = resolveAvatarUrl({
            avatarUrl: item.avatar_url,
            seed,
            name: item.company || name,
            size: 120,
        });

        stopFireworks(toastEl);
        toastEl.className = 'join-toast hidden';
        toastEl.innerHTML = `
            <img class="join-toast-avatar" src="${imageUrl}" alt="" width="52" height="52">
            <div class="join-toast-copy">
                <p class="join-toast-kicker">Just joined</p>
                <strong><span>${escapeHtml(name)}</span> is here!</strong>
                ${message ? `<p class="join-toast-message">${escapeHtml(message)}</p>` : ''}
                <small>${escapeHtml(item.company && item.first_name ? item.company : location)}</small>
            </div>
        `;

        toastEl.classList.remove('hidden');
        // Restart fly-in animation if another toast is already showing.
        toastEl.classList.remove('visible', 'leaving');
        void toastEl.offsetWidth;
        requestAnimationFrame(() => toastEl.classList.add('visible'));

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastEl.classList.add('leaving');
            toastEl.classList.remove('visible');
            setTimeout(() => {
                toastEl.classList.add('hidden');
                toastEl.classList.remove('leaving');
                toastEl.innerHTML = '';
            }, 380);
        }, 5000);
    }

    function openCompanyCard(rawProps) {
        const props = normalizeProps(rawProps);
        const seed = props.name || props.id || 'friend';
        const avatar = companyCard.querySelector('#company-card-avatar');
        avatar.src = resolveAvatarUrl({
            avatarUrl: props.avatar_url,
            seed,
            name: props.name,
            size: 128,
        });
        avatar.alt = props.name || 'Company avatar';

        companyCard.querySelector('#company-card-title').textContent = props.name || 'Company';
        companyCard.querySelector('#company-card-location').textContent = props.location || 'Malaysia';
        companyCard.querySelector('#company-card-services').innerHTML = (props.services || [])
            .map((service) => `<span class="expo-chip active" style="background:${service.color};color:#fff">${escapeHtml(service.name)}</span>`)
            .join('') || '<span class="expo-chip">Interests private</span>';
        companyCard.querySelector('#company-card-count').textContent = `${props.visitor_count || 1} friend${(props.visitor_count || 1) === 1 ? '' : 's'} checked in`;

        const website = companyCard.querySelector('#company-card-website');
        if (props.website && props.website !== 'null') {
            website.href = props.website;
            website.classList.remove('hidden');
        } else {
            website.classList.add('hidden');
        }

        companyCard.classList.remove('hidden');
        requestAnimationFrame(() => companyCard.classList.add('visible'));
    }

    function closeCompanyCard() {
        companyCard.classList.remove('visible');
        setTimeout(() => companyCard.classList.add('hidden'), 220);
    }

    function showMilestone(message) {
        milestone.textContent = message;
        milestone.classList.remove('hidden');
        requestAnimationFrame(() => milestone.classList.add('visible'));
        setTimeout(() => {
            milestone.classList.remove('visible');
            setTimeout(() => milestone.classList.add('hidden'), 280);
        }, 3200);
    }

    function showMapFallback(message) {
        let banner = root.querySelector('#map-fallback');
        if (! banner) {
            banner = document.createElement('div');
            banner.id = 'map-fallback';
            banner.className = 'map-fallback';
            root.appendChild(banner);
        }
        banner.textContent = message;
        banner.classList.remove('hidden');
    }

    function hideMapFallback() {
        root.querySelector('#map-fallback')?.classList.add('hidden');
    }
}

function renderCheckInQr(url) {
    const canvas = document.getElementById('checkin-qr');
    const link = document.getElementById('checkin-qr-link');
    if (link) {
        link.href = url;
    }
    if (! canvas) {
        return;
    }

    const drawWithLibrary = () => {
        if (typeof window.QRCode?.toCanvas !== 'function') {
            return false;
        }

        window.QRCode.toCanvas(canvas, url, {
            width: 148,
            margin: 1,
            color: {
                dark: '#1d1d1f',
                light: '#ffffff',
            },
        }, (error) => {
            if (error) {
                console.error('QR render failed', error);
                useImageFallback();
            }
        });

        return true;
    };

    const useImageFallback = () => {
        const img = document.createElement('img');
        img.id = 'checkin-qr-img';
        img.width = 148;
        img.height = 148;
        img.alt = 'QR code for check-in';
        img.src = `https://api.qrserver.com/v1/create-qr-code/?size=148x148&margin=8&data=${encodeURIComponent(url)}`;
        canvas.replaceWith(img);
    };

    if (! drawWithLibrary()) {
        // CDN script may still be loading.
        let attempts = 0;
        const timer = setInterval(() => {
            attempts += 1;
            if (drawWithLibrary() || attempts >= 20) {
                clearInterval(timer);
                if (attempts >= 20 && typeof window.QRCode?.toCanvas !== 'function') {
                    useImageFallback();
                }
            }
        }, 150);
    }
}

function startFireworks(canvas) {
    if (! canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    if (! ctx) {
        return;
    }

    const colors = ['#007aff', '#ff2d55', '#ff9500', '#34c759', '#af52de', '#5ac8fa', '#ffd60a'];
    const rockets = [];
    const sparks = [];
    let frame = 0;
    let lastBurst = 0;

    const resize = () => {
        canvas.width = canvas.clientWidth * window.devicePixelRatio;
        canvas.height = canvas.clientHeight * window.devicePixelRatio;
        ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
    };

    resize();

    const launch = () => {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        rockets.push({
            x: w * (0.15 + Math.random() * 0.7),
            y: h,
            tx: w * (0.2 + Math.random() * 0.6),
            ty: h * (0.18 + Math.random() * 0.28),
            life: 0,
            color: colors[Math.floor(Math.random() * colors.length)],
        });
    };

    const burst = (x, y, color) => {
        const count = 52 + Math.floor(Math.random() * 28);
        for (let i = 0; i < count; i += 1) {
            const angle = (Math.PI * 2 * i) / count + Math.random() * 0.25;
            const speed = 3.2 + Math.random() * 5.8;
            sparks.push({
                x,
                y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                life: 1,
                decay: 0.01 + Math.random() * 0.014,
                color: Math.random() > 0.3 ? color : colors[Math.floor(Math.random() * colors.length)],
                size: 2.4 + Math.random() * 3.6,
            });
        }
    };

    // Immediate opening bursts for impact.
    for (let i = 0; i < 7; i += 1) {
        launch();
    }

    const tick = () => {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        ctx.clearRect(0, 0, w, h);
        frame += 1;

        if (frame - lastBurst > 10) {
            launch();
            lastBurst = frame;
        }

        for (let i = rockets.length - 1; i >= 0; i -= 1) {
            const rocket = rockets[i];
            rocket.life += 0.05;
            const t = Math.min(1, rocket.life);
            const x = rocket.x + (rocket.tx - rocket.x) * t;
            const y = rocket.y + (rocket.ty - rocket.y) * (t * t);
            ctx.beginPath();
            ctx.fillStyle = rocket.color;
            ctx.shadowBlur = 12;
            ctx.shadowColor = rocket.color;
            ctx.arc(x, y, 3.2, 0, Math.PI * 2);
            ctx.fill();
            ctx.shadowBlur = 0;

            if (t >= 1) {
                burst(x, y, rocket.color);
                rockets.splice(i, 1);
            }
        }

        for (let i = sparks.length - 1; i >= 0; i -= 1) {
            const spark = sparks[i];
            spark.x += spark.vx;
            spark.y += spark.vy;
            spark.vy += 0.05;
            spark.vx *= 0.985;
            spark.life -= spark.decay;

            if (spark.life <= 0) {
                sparks.splice(i, 1);
                continue;
            }

            ctx.globalAlpha = Math.max(0, spark.life);
            ctx.beginPath();
            ctx.fillStyle = spark.color;
            ctx.shadowBlur = 10;
            ctx.shadowColor = spark.color;
            ctx.arc(spark.x, spark.y, spark.size, 0, Math.PI * 2);
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.globalAlpha = 1;
        }

        canvas._fireworksRaf = requestAnimationFrame(tick);
    };

    canvas._fireworksResize = resize;
    window.addEventListener('resize', resize);
    canvas._fireworksRaf = requestAnimationFrame(tick);
}

function stopFireworks(root) {
    const canvas = root?.querySelector?.('.join-fireworks') || (root?.classList?.contains('join-fireworks') ? root : null);
    if (! canvas) {
        return;
    }
    if (canvas._fireworksRaf) {
        cancelAnimationFrame(canvas._fireworksRaf);
        canvas._fireworksRaf = null;
    }
    if (canvas._fireworksResize) {
        window.removeEventListener('resize', canvas._fireworksResize);
        canvas._fireworksResize = null;
    }
}

function chipButton(label, slug, active = false, color = null) {
    const activeClass = active ? 'active' : '';
    const bg = active ? `style="background:${color || '#007aff'};color:#fff"` : '';
    return `<button type="button" class="expo-chip ${activeClass}" data-service="${slug ?? ''}" ${bg}>${escapeHtml(label)}</button>`;
}

function normalizeProps(props) {
    const services = typeof props.services === 'string' ? JSON.parse(props.services || '[]') : (props.services || []);
    return { ...props, services };
}

function animateNumber(el, value) {
    if (! el) {
        return;
    }
    const from = Number(el.textContent) || 0;
    const to = Number(value) || 0;
    if (from === to) {
        el.textContent = String(to);
        return;
    }
    const start = performance.now();
    const duration = 450;
    function tick(now) {
        const progress = Math.min(1, (now - start) / duration);
        el.textContent = String(Math.round(from + (to - from) * progress));
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    }
    requestAnimationFrame(tick);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
