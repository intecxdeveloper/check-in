import { createCheckInWizard } from './checkin-wizard';
import { avatarUrl, resolveAvatarUrl } from './avatars';

export function createExpoMap(root, maplibregl) {
    const mapStyle = root.dataset.mapStyle;
    const defaultPitch = Number(root.dataset.mapPitch ?? 48);
    const maxPitch = Number(root.dataset.mapMaxPitch ?? 68);
    const defaultBearing = Number(root.dataset.mapBearing ?? -12);
    const terrainEnabled = root.dataset.mapTerrain !== '0';
    const terrainExaggeration = Number(root.dataset.mapTerrainExaggeration ?? 1.25);
    const startCenter = [
        Number(root.dataset.mapCenterLng ?? 101.9758),
        Number(root.dataset.mapCenterLat ?? 4.2105),
    ];
    const startZoom = Number(root.dataset.mapStartZoom ?? 5.6);
    const maxZoom = Number(root.dataset.mapMaxZoom ?? 16.5);
    const services = JSON.parse(root.dataset.services || '[]');
    const checkInUrl = root.dataset.checkInUrl || '/check-in';
    const knownIds = new Set();
    const knownVisitorIds = new Set();
    const markersById = new Map();
    let activeService = null;
    let lastRegionCount = 0;
    let mapReady = false;
    let map = null;
    let overviewFitPending = false;
    let userMovedMap = false;
    let recentBootstrapped = false;
    let toastTimer = null;
    let toastLeaveTimer = null;

    const statsEls = {
        check_ins: root.querySelector('[data-stat="check_ins"]'),
        companies: root.querySelector('[data-stat="companies"]'),
        regions: root.querySelector('[data-stat="regions"]'),
    };

    const filtersEl = root.querySelector('#service-filters');
    const recentList = root.querySelector('#recent-list');
    const liveCard = root.querySelector('#expo-live') || recentList?.closest('.expo-live');
    let liveCardTimer = null;
    const companyCard = root.querySelector('#company-card');
    const milestone = root.querySelector('#milestone');
    const toastEl = root.querySelector('#checkin-toast');
    const fireworksEl = root.querySelector('#join-fireworks');

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
                    el.style.background = service?.color || '#0058b8';
                    el.style.color = '#fff';
                } else {
                    el.style.background = '';
                    el.style.color = '';
                }
            });
            refreshCompanies({ animateNew: false, fitOverview: true });
        });
    }

    root.querySelector('#company-card-close')?.addEventListener('click', closeCompanyCard);

    let pollInFlight = false;
    let pollTick = 0;
    let lastPolledCheckIns = null;
    refreshStats()
        .then((count) => { lastPolledCheckIns = count; })
        .catch((error) => console.error(error));
    refreshRecent({ announce: false }).catch((error) => console.error(error));
    setInterval(async () => {
        if (pollInFlight || document.hidden) {
            return;
        }
        pollInFlight = true;
        pollTick += 1;
        try {
            // Stats are tiny; only pull the heavier feed + map payload when the
            // check-in count moves (or every ~minute as a safety net).
            const checkIns = await refreshStats().catch((error) => {
                console.error(error);
                return null;
            });
            const changed = checkIns !== null && checkIns !== lastPolledCheckIns;
            const safetyNet = pollTick % 12 === 0;
            lastPolledCheckIns = checkIns ?? lastPolledCheckIns;

            if (changed || safetyNet) {
                await Promise.all([
                    refreshRecent({ announce: true }).catch((error) => console.error(error)),
                    mapReady
                        ? refreshCompanies({ animateNew: true }).catch((error) => console.error(error))
                        : Promise.resolve(),
                ]);
            }
        } finally {
            pollInFlight = false;
        }
    }, 5000);

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
                    zoom: Math.max(map.getZoom(), 14.2),
                    pitch: Math.min(maxPitch, Math.max(defaultPitch, 55)),
                    bearing: defaultBearing,
                    duration: 2000,
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

    function enableOsm3dScene() {
        try {
            map.setProjection({ type: 'mercator' });
        } catch (_) {
            // Ignore if projection API is unavailable.
        }

        if (map.getLayer('building-3d')) {
            try {
                map.setPaintProperty('building-3d', 'fill-extrusion-opacity', 0.92);
                map.setLayerZoomRange('building-3d', 13, 24);
            } catch (_) {
                // Style may omit paint APIs on older builds.
            }
        }

        if (terrainEnabled && ! map.getSource('osm-terrain')) {
            try {
                map.addSource('osm-terrain', {
                    type: 'raster-dem',
                    tiles: ['https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'],
                    encoding: 'terrarium',
                    tileSize: 256,
                    maxzoom: 15,
                    attribution: 'Terrain © Mapzen / AWS',
                });
                map.setTerrain({
                    source: 'osm-terrain',
                    exaggeration: terrainExaggeration,
                });
            } catch (error) {
                console.warn('OSM terrain unavailable', error);
            }
        }

        try {
            map.setSky({
                'sky-color': '#b9dcff',
                'sky-horizon-blend': 0.55,
                'horizon-color': '#f5f7fa',
                'horizon-fog-blend': 0.85,
                'fog-color': '#ffffff',
                'fog-ground-blend': 0.35,
            });
        } catch (_) {
            // Sky is optional for pitched views.
        }
    }

    function initMap() {
        // Mercator OSM scene. DOM pins stay locked via map.project().
        map = new maplibregl.Map({
            container: 'map',
            style: mapStyle,
            center: startCenter,
            zoom: startZoom,
            pitch: 0,
            bearing: 0,
            maxPitch,
            minPitch: 0,
            maxZoom,
            minZoom: 3,
            renderWorldCopies: false,
            dragRotate: true,
            touchPitch: true,
            attributionControl: false,
            failIfMajorPerformanceCaveat: false,
        });

        map.on('error', (event) => {
            console.error('MapLibre error', event?.error || event);
        });

        map.on('style.load', () => {
            enableOsm3dScene();
        });

        ensureMarkerLayer();
        // Pins are positioned synchronously inside MapLibre's own frame ('move' fires
        // right before it paints), so they never trail the basemap by a frame while
        // dragging. Decluttering only reruns when the zoom level settles.
        map.on('move', syncMarkerPositions);
        map.on('dragstart', markUserMovedMap);
        map.on('rotatestart', markUserMovedMap);
        map.on('pitchstart', markUserMovedMap);
        map.on('zoomstart', () => {
            if (! overviewFitPending && ! expandingCluster) {
                markUserMovedMap();
            }
        });
        map.on('resize', () => {
            readMapSize();
            if (! expandingCluster && ! userMovedMap) {
                fitMapToAllPins({ animate: false });
            } else if (! expandingCluster) {
                declutterMarkers();
            }
            syncMarkerPositions();
        });
        map.on('zoomend', () => {
            if (expandingCluster) {
                return;
            }
            declutterMarkers();
            syncMarkerPositions();
        });
        map.on('pitchend', syncMarkerPositions);
        map.on('rotateend', syncMarkerPositions);

        const loadTimeout = setTimeout(() => {
            if (! mapReady) {
                showMapFallback('Map tiles are slow to load. Showing counters while we keep trying…');
            }
        }, 8000);

        map.on('load', async () => {
            clearTimeout(loadTimeout);
            hideMapFallback();
            root.querySelector('#map-loading')?.remove();
            enableOsm3dScene();

            mapReady = true;
            window.__expoMap = map;
            await refreshCompanies({ animateNew: false, fitOverview: true });
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

    let mapWidth = 0;
    let mapHeight = 0;
    const OFFSCREEN_MARGIN_PX = 120;

    function readMapSize() {
        const container = map?.getContainer();
        mapWidth = container?.clientWidth || 0;
        mapHeight = container?.clientHeight || 0;
    }

    function worldPixel(lng, lat, worldSize) {
        const sin = Math.sin((Math.max(-85, Math.min(85, lat)) * Math.PI) / 180);
        const x = ((lng + 180) / 360) * worldSize;
        const y = (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * worldSize;

        return { x, y };
    }

    // Distance-cluster in screen pixels so faces never sit on top of each other.
    // Recluster only when the camera settles — not on every zoom frame.
    const CLUSTER_RADIUS_PX = 64;
    const forcedSpiderfyIds = new Set();
    const SAME_LOCATION_DEG = 0.00035;
    const SPIDERFY_MAX = 16;
    let expandingCluster = false;
    let spiderRaf = 0;
    let spiderLegsEl = null;

    function mapMaxZoom() {
        return map?.getMaxZoom?.() || maxZoom;
    }

    function groupCenter(group) {
        let sumLng = 0;
        let sumLat = 0;
        for (const entry of group) {
            sumLng += entry.lngLat.lng;
            sumLat += entry.lngLat.lat;
        }

        return {
            lng: sumLng / group.length,
            lat: sumLat / group.length,
        };
    }

    function isSameLocationGroup(group) {
        if (group.length < 2) {
            return false;
        }

        const center = groupCenter(group);
        return group.every((entry) => (
            Math.abs(entry.lngLat.lng - center.lng) <= SAME_LOCATION_DEG
            && Math.abs(entry.lngLat.lat - center.lat) <= SAME_LOCATION_DEG
        ));
    }

    function clusterGroupsAtZoom(entries, zoom) {
        const worldSize = 512 * 2 ** zoom;
        const points = entries.map((entry) => ({
            entry,
            ...worldPixel(entry.lngLat.lng, entry.lngLat.lat, worldSize),
        }));
        const parent = points.map((_, index) => index);
        const find = (index) => {
            if (parent[index] !== index) {
                parent[index] = find(parent[index]);
            }

            return parent[index];
        };
        const radiusSq = CLUSTER_RADIUS_PX * CLUSTER_RADIUS_PX;

        for (let i = 0; i < points.length; i += 1) {
            for (let j = i + 1; j < points.length; j += 1) {
                const dx = points[i].x - points[j].x;
                const dy = points[i].y - points[j].y;
                if ((dx * dx) + (dy * dy) <= radiusSq) {
                    parent[find(i)] = find(j);
                }
            }
        }

        const groups = new Map();
        points.forEach((point, index) => {
            const root = find(index);
            if (! groups.has(root)) {
                groups.set(root, []);
            }
            groups.get(root).push(point.entry);
        });

        return [...groups.values()];
    }

    function zoomToUncluster(group, fromZoom) {
        if (isSameLocationGroup(group)) {
            return null;
        }

        const ceiling = mapMaxZoom();
        for (let zoom = Math.max(0, fromZoom) + 0.2; zoom <= ceiling + 0.001; zoom += 0.2) {
            const level = Math.min(ceiling, zoom);
            if (clusterGroupsAtZoom(group, level).length === group.length) {
                return Math.min(ceiling, level + 0.1);
            }
        }

        return null;
    }

    function memberBounds(group) {
        if (! maplibregl || ! group.length) {
            return null;
        }

        const first = group[0].lngLat;
        const bounds = new maplibregl.LngLatBounds(first, first);
        for (const entry of group) {
            bounds.extend(entry.lngLat);
        }

        return bounds;
    }

    function markUserMovedMap() {
        if (overviewFitPending || expandingCluster) {
            return;
        }

        userMovedMap = true;
    }

    function overviewPadding() {
        const width = map?.getContainer()?.clientWidth || window.innerWidth;
        const mobile = width <= 720;
        const marker = 40;
        if (mobile) {
            return {
                top: 80 + marker,
                bottom: 96 + marker,
                left: 24 + marker,
                right: 24 + marker,
            };
        }

        return {
            top: 96 + marker,
            bottom: 56 + marker,
            left: 32 + marker,
            right: 168 + marker,
        };
    }

    function allPinsOnScreen() {
        if (! map || ! markersById.size) {
            return true;
        }

        if (! mapWidth) {
            readMapSize();
        }

        const pad = overviewPadding();
        const markerPad = 28;

        for (const entry of markersById.values()) {
            const point = map.project(entry.lngLat);
            if (
                point.x < pad.left + markerPad
                || point.x > mapWidth - pad.right - markerPad
                || point.y < pad.top + markerPad
                || point.y > mapHeight - pad.bottom - markerPad
            ) {
                return false;
            }
        }

        return true;
    }

    function fitMapToAllPins({ animate = false } = {}) {
        if (! map || expandingCluster || markersById.size === 0) {
            return;
        }

        const bounds = memberBounds([...markersById.values()]);
        if (! bounds) {
            return;
        }

        const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = animate && ! reduceMotion ? 720 : 0;
        overviewFitPending = true;
        let finished = false;

        const finish = () => {
            if (finished) {
                return;
            }
            finished = true;
            overviewFitPending = false;
            declutterMarkers();
            syncMarkerPositions();
        };
        const safety = setTimeout(finish, duration + 420);
        map.once('moveend', () => {
            clearTimeout(safety);
            finish();
        });

        try {
            map.fitBounds(bounds, {
                padding: overviewPadding(),
                maxZoom: 10.8,
                pitch: 0,
                bearing: 0,
                duration,
                essential: true,
            });
        } catch (_) {
            clearTimeout(safety);
            overviewFitPending = false;
            map.jumpTo({
                center: startCenter,
                zoom: startZoom,
                pitch: defaultPitch,
                bearing: defaultBearing,
            });
        }
    }

    function pickClusterLead(group) {
        return group.find((entry) => entry.isCluster) || group[0];
    }

    function spiderfyOffsets(count) {
        const shown = Math.min(count, SPIDERFY_MAX);
        const offsets = [];
        if (shown === 1) {
            return [{ x: 0, y: 0 }];
        }

        const radius = shown <= 8 ? 46 : shown <= 12 ? 58 : 70;
        for (let i = 0; i < shown; i += 1) {
            const angle = ((Math.PI * 2) * i) / shown - Math.PI / 2;
            offsets.push({
                x: Math.cos(angle) * radius,
                y: Math.sin(angle) * radius,
            });
        }

        return offsets;
    }

    function ensureSpiderLegs() {
        const layer = ensureMarkerLayer();
        if (! layer) {
            return null;
        }
        if (spiderLegsEl && spiderLegsEl.isConnected) {
            return spiderLegsEl;
        }

        spiderLegsEl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        spiderLegsEl.classList.add('avatar-spider-legs');
        spiderLegsEl.setAttribute('aria-hidden', 'true');
        layer.prepend(spiderLegsEl);
        return spiderLegsEl;
    }

    function clearSpiderLegs() {
        if (spiderLegsEl) {
            spiderLegsEl.replaceChildren();
        }
    }

    function drawSpiderLegs(centerPoint, group) {
        const svg = ensureSpiderLegs();
        if (! svg || ! mapWidth) {
            return;
        }

        svg.setAttribute('width', String(mapWidth));
        svg.setAttribute('height', String(mapHeight));
        svg.setAttribute('viewBox', `0 0 ${mapWidth} ${mapHeight}`);

        const lines = [];
        for (const entry of group) {
            if (entry.clustered || ! entry.isSpiderfy) {
                continue;
            }
            const ox = entry.spiderOffset?.x || 0;
            const oy = entry.spiderOffset?.y || 0;
            if (Math.hypot(ox, oy) < 4) {
                continue;
            }
            lines.push(
                `<line x1="${centerPoint.x.toFixed(1)}" y1="${centerPoint.y.toFixed(1)}" x2="${(centerPoint.x + ox).toFixed(1)}" y2="${(centerPoint.y + oy).toFixed(1)}" />`
            );
        }
        svg.innerHTML = lines.join('');
    }

    function assignBubble(entry, bubbleTaken, bubbleCellPx, worldSize) {
        const message = String(entry.props?.message || '').trim();
        if (! message || entry.isCluster || entry.isSpiderfy) {
            entry.showBubble = false;
            return;
        }

        const lngLat = entry.renderLngLat || entry.lngLat;
        const { x, y } = worldPixel(lngLat.lng, lngLat.lat, worldSize);
        const bubbleCell = `${Math.floor(x / bubbleCellPx)}:${Math.floor(y / bubbleCellPx)}`;
        entry.showBubble = entry.pinned || ! bubbleTaken.has(bubbleCell);
        if (entry.showBubble) {
            bubbleTaken.add(bubbleCell);
        }
    }

    function applyClusterGroup(group) {
        const lead = pickClusterLead(group);
        const center = groupCenter(group);

        group.forEach((entry) => {
            const isLead = entry === lead;
            entry.clustered = ! isLead;
            entry.isCluster = isLead;
            entry.isSpiderfy = false;
            entry.clusterCount = isLead ? group.length : 0;
            entry.clusterMembers = isLead ? group : null;
            entry.renderLngLat = center;
            entry.spiderOffset = { x: 0, y: 0 };
            entry.showBubble = false;
            entry.shown = isLead;
            entry.el.style.display = isLead ? '' : 'none';
            entry.el.classList.toggle('avatar-marker--cluster', isLead);
            entry.el.classList.toggle('avatar-marker--spiderfy', false);
            entry.el.style.zIndex = isLead ? '8' : '';
        });

        updateClusterMarker(lead, group);
    }

    function applySpiderfyGroup(group, animate) {
        const center = groupCenter(group);
        const shown = group.slice(0, SPIDERFY_MAX);
        const hidden = group.slice(SPIDERFY_MAX);
        const offsets = spiderfyOffsets(shown.length);

        hidden.forEach((entry) => {
            entry.clustered = true;
            entry.isCluster = false;
            entry.isSpiderfy = false;
            entry.clusterCount = 0;
            entry.clusterMembers = null;
            entry.spiderOffset = { x: 0, y: 0 };
            entry.showBubble = false;
            entry.shown = false;
            entry.el.style.display = 'none';
            entry.el.classList.toggle('avatar-marker--cluster', false);
            entry.el.classList.toggle('avatar-marker--spiderfy', false);
        });

        shown.forEach((entry, index) => {
            entry.clustered = false;
            entry.isCluster = false;
            entry.isSpiderfy = true;
            entry.clusterCount = 0;
            entry.clusterMembers = shown;
            entry.renderLngLat = center;
            entry.targetOffset = offsets[index] || { x: 0, y: 0 };
            entry.spiderOffset = animate ? { x: 0, y: 0 } : entry.targetOffset;
            entry.showBubble = false;
            entry.el.classList.toggle('avatar-marker--cluster', false);
            entry.el.classList.toggle('avatar-marker--spiderfy', true);
            entry.el.style.zIndex = String(24 + (shown.length - index));
            updateClusterMarker(entry, null);
            entry.el.setAttribute(
                'aria-label',
                `${entry.props?.name || 'Visitor'} (${index + 1} of ${group.length} at this spot)`
            );
        });

        if (animate) {
            animateSpiderOffsets(shown, center);
        }
    }

    function animateSpiderOffsets(group, center) {
        cancelAnimationFrame(spiderRaf);
        const start = performance.now();
        const duration = matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : 340;

        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - ((1 - t) ** 3);
            for (const entry of group) {
                const target = entry.targetOffset || { x: 0, y: 0 };
                entry.spiderOffset = {
                    x: target.x * eased,
                    y: target.y * eased,
                };
            }
            syncMarkerPositions();
            if (map) {
                drawSpiderLegs(map.project(center), group);
            }
            if (t < 1) {
                spiderRaf = requestAnimationFrame(tick);
            }
        };

        spiderRaf = requestAnimationFrame(tick);
    }

    function applySingleMarker(entry, bubbleTaken, bubbleCellPx, worldSize) {
        entry.clustered = false;
        entry.isCluster = false;
        entry.isSpiderfy = false;
        entry.clusterCount = 0;
        entry.clusterMembers = null;
        entry.renderLngLat = entry.lngLat;
        entry.spiderOffset = { x: 0, y: 0 };
        entry.shown = true;
        entry.el.style.display = '';
        entry.el.classList.toggle('avatar-marker--cluster', false);
        entry.el.classList.toggle('avatar-marker--spiderfy', false);
        entry.el.style.zIndex = '';
        updateClusterMarker(entry, null);
        assignBubble(entry, bubbleTaken, bubbleCellPx, worldSize);
    }

    function declutterMarkers({ animateSpider = false } = {}) {
        if (! map) {
            return;
        }

        const zoom = map.getZoom();
        const worldSize = 512 * 2 ** zoom;
        const bubbleCellPx = zoom < 6 ? 150 : 112;
        const bubbleTaken = new Set();
        const entries = [...markersById.values()].sort((a, b) => {
            if (a.pinned !== b.pinned) {
                return a.pinned ? -1 : 1;
            }
            return b.rank - a.rank;
        });

        if (zoom < 8.2) {
            forcedSpiderfyIds.clear();
        }

        const groups = clusterGroupsAtZoom(entries, zoom);
        let spiderGroup = null;
        for (const group of groups) {
            if (group.length === 1) {
                applySingleMarker(group[0], bubbleTaken, bubbleCellPx, worldSize);
                continue;
            }

            const forced = group.some((entry) => forcedSpiderfyIds.has(String(entry.props?.id)));
            if (forced && zoom >= 8.6) {
                applySpiderfyGroup(group, animateSpider);
                spiderGroup = group;
            } else {
                applyClusterGroup(group);
            }
        }

        if (spiderGroup) {
            const center = groupCenter(spiderGroup);
            drawSpiderLegs(map.project(center), spiderGroup);
        } else {
            clearSpiderLegs();
        }
    }

    function updateClusterMarker(entry, group) {
        const count = group?.length || 0;
        const isCluster = count > 1;
        let badge = entry.el.querySelector('.avatar-marker-count');

        if (! isCluster) {
            if (! entry.isSpiderfy) {
                entry.el.setAttribute('aria-label', entry.props?.name || 'Company');
            }
            badge?.remove();
            return;
        }

        entry.el.setAttribute(
            'aria-label',
            `${count} visitors near ${entry.props?.name || 'this area'}. Tap to zoom until pins separate.`
        );

        if (! badge) {
            badge = document.createElement('span');
            badge.className = 'avatar-marker-count';
            badge.setAttribute('aria-hidden', 'true');
            entry.el.appendChild(badge);
        }
        const label = String(count);
        if (badge.textContent !== label) {
            badge.textContent = label;
        }
    }

    function expandCluster(entry) {
        if (! map || expandingCluster || ! entry?.clusterMembers?.length) {
            return;
        }

        const members = entry.clusterMembers;
        const sameSpot = isSameLocationGroup(members);
        const center = groupCenter(members);
        const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = reduceMotion ? 1 : 860;
        const unclusterZoom = sameSpot ? null : zoomToUncluster(members, map.getZoom());
        const bounds = memberBounds(members);

        let finished = false;
        const finish = () => {
            if (finished) {
                return;
            }
            finished = true;
            expandingCluster = false;
            if (sameSpot) {
                for (const member of members) {
                    forcedSpiderfyIds.add(String(member.props?.id ?? member.rank));
                }
            }
            declutterMarkers({ animateSpider: sameSpot });
            syncMarkerPositions();
        };

        expandingCluster = true;
        const safety = setTimeout(finish, duration + 420);
        map.once('moveend', () => {
            clearTimeout(safety);
            finish();
        });

        if (sameSpot || ! bounds) {
            map.easeTo({
                center: [center.lng, center.lat],
                zoom: Math.min(mapMaxZoom(), Math.max(map.getZoom() + 1.6, 14.5)),
                duration,
                essential: true,
            });
            return;
        }

        map.fitBounds(bounds, {
            padding: { top: 80, bottom: 128, left: 44, right: 44 },
            maxZoom: unclusterZoom ?? mapMaxZoom(),
            duration,
            essential: true,
        });
    }

    function setMarkerShown(entry, shown) {
        if (entry.shown === shown) {
            return;
        }
        entry.shown = shown;
        entry.el.style.display = shown ? '' : 'none';
    }

    function setMarkerBubble(entry, showBubble) {
        if (entry.bubbleShown === showBubble) {
            return;
        }
        entry.bubbleShown = showBubble;
        entry.el.classList.toggle('avatar-marker--bubble-hidden', ! showBubble);
    }

    function syncMarkerPositions() {
        if (! map) {
            return;
        }
        if (! mapWidth) {
            readMapSize();
        }

        let spiderCenter = null;
        let spiderGroup = [];

        for (const entry of markersById.values()) {
            if (entry.clustered) {
                setMarkerShown(entry, false);
                setMarkerBubble(entry, false);
                continue;
            }

            const lngLat = entry.renderLngLat || entry.lngLat;
            const point = map.project(lngLat);
            const ox = entry.spiderOffset?.x || 0;
            const oy = entry.spiderOffset?.y || 0;
            const screenX = point.x + ox;
            const screenY = point.y + oy;
            const visible = screenX > -OFFSCREEN_MARGIN_PX && screenY > -OFFSCREEN_MARGIN_PX
                && screenX < mapWidth + OFFSCREEN_MARGIN_PX
                && screenY < mapHeight + OFFSCREEN_MARGIN_PX;

            setMarkerShown(entry, visible);
            setMarkerBubble(entry, visible && entry.showBubble !== false);
            if (visible) {
                entry.el.style.transform = `translate3d(${Math.round(screenX)}px, ${Math.round(screenY)}px, 0) translate(-50%, -100%)`;
            }

            if (entry.isSpiderfy) {
                spiderCenter = point;
                spiderGroup.push(entry);
            }
        }

        if (spiderCenter && spiderGroup.length) {
            drawSpiderLegs(spiderCenter, spiderGroup);
        } else {
            clearSpiderLegs();
        }
    }

    async function refreshCompanies({ animateNew = true, highlightId = null, fitOverview = false } = {}) {
        if (! mapReady || ! map) {
            return;
        }

        const params = new URLSearchParams();
        if (activeService) {
            params.set('service', activeService);
        }

        const data = await window.expoFetch(`/api/map/companies?${params.toString()}`);
        const incoming = Array.isArray(data.features) ? data.features : [];
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
                entry.pinned = shouldPop;
                updatePeopleMarker(entry.el, props, shouldPop);
            } else {
                const el = createPeopleMarker(props, shouldPop);
                layer.appendChild(el);
                markersById.set(id, {
                    el,
                    lngLat,
                    props,
                    rank: Number(id) || 0,
                    pinned: shouldPop,
                    shown: true,
                    clustered: false,
                    isCluster: false,
                    isSpiderfy: false,
                    showBubble: true,
                    bubbleShown: true,
                    renderLngLat: lngLat,
                    spiderOffset: { x: 0, y: 0 },
                    clusterCount: 0,
                    clusterMembers: null,
                });
            }

            knownIds.add(Number(id));
            knownIds.add(id);
        }

        declutterMarkers();
        syncMarkerPositions();

        if (! highlightId && (fitOverview || (! userMovedMap && ! allPinsOnScreen()))) {
            fitMapToAllPins({ animate: animateNew && ! fitOverview });
        }

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
        el.dataset.markerId = String(props.id ?? '');
        updatePeopleMarker(el, props, pop);

        // Keep map drag/rotate from swallowing marker taps.
        const stopMapGesture = (event) => event.stopPropagation();
        el.addEventListener('mousedown', stopMapGesture);
        el.addEventListener('touchstart', stopMapGesture, { passive: true });
        el.addEventListener('dblclick', stopMapGesture);
        el.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const markerId = String(el.dataset.markerId || el._props?.id || props.id || '');
            const entry = markersById.get(markerId);
            const isCluster = Boolean(
                ! entry?.isSpiderfy
                && (entry?.isCluster || el.classList.contains('avatar-marker--cluster'))
                && entry?.clusterMembers?.length > 1
            );

            if (isCluster) {
                expandCluster(entry);
                return;
            }

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
        el.dataset.markerId = String(props.id ?? el.dataset.markerId ?? '');
        if (! el.classList.contains('avatar-marker--cluster')) {
            el.setAttribute('aria-label', props.name || 'Company');
        }
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

        return stats.check_ins;
    }

    async function refreshRecent({ announce = true } = {}) {
        if (! recentList) {
            return;
        }

        const data = await window.expoFetch('/api/map/recent');
        const items = data.items || [];
        let revealLive = false;

        if (! recentBootstrapped) {
            items.forEach((item) => knownVisitorIds.add(Number(item.id)));
            recentBootstrapped = true;
            revealLive = items.some((item) => item.public);
        } else if (announce) {
            const newcomers = items
                .filter((item) => ! knownVisitorIds.has(Number(item.id)))
                .sort((a, b) => Number(a.id) - Number(b.id));
            revealLive = newcomers.some((item) => item.public);

            for (const item of newcomers) {
                knownVisitorIds.add(Number(item.id));
                showCheckInToast(item);

                if (item.public && item.longitude != null && item.latitude != null && mapReady && map) {
                    map.flyTo({
                        center: [Number(item.longitude), Number(item.latitude)],
                        zoom: Math.max(map.getZoom(), 14),
                        pitch: Math.min(maxPitch, Math.max(defaultPitch, 55)),
                        bearing: defaultBearing,
                        duration: 1800,
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
            const publicItems = items.filter((item) => item.public);
            recentList.innerHTML = publicItems.slice(0, 6).map((item) => {
                const message = String(item.message || '').trim();
                const title = item.company || 'A new friend';
                const place = item.location || 'Malaysia';
                const image = item.avatar_url || avatarUrl(item.company || 'friend', 72);
                return `
                    <li class="${message ? 'has-message' : ''}">
                        <img class="mini-avatar" src="${image}" alt="" width="36" height="36" loading="lazy">
                        <strong>${escapeHtml(title)}</strong>
                        ${message ? `<em class="ticker-message">${escapeHtml(message)}</em>` : ''}
                        <small>${escapeHtml(place)} · ${escapeHtml(item.at || 'just now')}</small>
                    </li>
                `;
            }).join('') || '<li class="expo-ticker-empty"><small>Be the first pin on the map.</small></li>';

            if (publicItems.length && revealLive) {
                showLiveCard();
            } else if (! publicItems.length) {
                hideLiveCard();
            }
        }
    }

    function showLiveCard() {
        if (! liveCard) {
            return;
        }

        liveCard.classList.remove('is-hidden');
        liveCard.setAttribute('aria-hidden', 'false');
        clearTimeout(liveCardTimer);
        liveCardTimer = setTimeout(hideLiveCard, 7000);
    }

    function hideLiveCard() {
        if (! liveCard) {
            return;
        }

        liveCard.classList.add('is-hidden');
        liveCard.setAttribute('aria-hidden', 'true');
    }

    function hideJoinCelebration() {
        clearTimeout(toastTimer);
        clearTimeout(toastLeaveTimer);
        stopFireworks(fireworksEl);
        fireworksEl?.classList.add('hidden');

        if (! toastEl || toastEl.classList.contains('hidden')) {
            return;
        }

        toastEl.classList.add('leaving');
        toastEl.classList.remove('visible');
        toastLeaveTimer = setTimeout(() => {
            toastEl.classList.add('hidden');
            toastEl.classList.remove('leaving');
            toastEl.innerHTML = '';
        }, 320);
    }

    function showCheckInToast(item) {
        if (! toastEl) {
            return;
        }

        const seed = item.company || item.first_name || 'friend';
        const name = String(item.first_name || item.company || 'A new friend').trim();
        const message = String(item.message || '').trim();
        const location = String(item.location || 'Malaysia').trim();
        const companyLine = item.company && item.first_name ? item.company : location;
        const imageUrl = resolveAvatarUrl({
            avatarUrl: item.avatar_url,
            seed,
            name: item.company || name,
            size: 120,
        });
        const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

        clearTimeout(toastTimer);
        clearTimeout(toastLeaveTimer);
        stopFireworks(fireworksEl);

        toastEl.className = 'join-toast join-toast--celebrate hidden';
        toastEl.innerHTML = `
            <span class="join-toast-sparkles" aria-hidden="true">
                <i></i><i></i><i></i><i></i><i></i><i></i>
            </span>
            <img class="join-toast-avatar" src="${imageUrl}" alt="" width="56" height="56">
            <div class="join-toast-copy">
                <p class="join-toast-kicker">Welcome to the map</p>
                <strong><span>${escapeHtml(name)}</span> just joined!</strong>
                ${message ? `<p class="join-toast-message">${escapeHtml(message)}</p>` : ''}
                <small>${escapeHtml(companyLine)}</small>
            </div>
        `;

        toastEl.classList.remove('hidden');
        toastEl.classList.remove('visible', 'leaving');
        void toastEl.offsetWidth;
        requestAnimationFrame(() => toastEl.classList.add('visible'));

        if (fireworksEl && ! reduceMotion) {
            fireworksEl.classList.remove('hidden');
            startFireworks(fireworksEl);
        }

        toastTimer = setTimeout(() => hideJoinCelebration(), 3000);
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
            .map((service) => `<span class="expo-chip active" style="background:${safeColor(service.color)};color:#fff">${escapeHtml(service.name)}</span>`)
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
        root.querySelector('#map-loading')?.remove();
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

    // Immediate opening bursts for a short celebration.
    for (let i = 0; i < 9; i += 1) {
        launch();
    }

    const tick = () => {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        ctx.clearRect(0, 0, w, h);
        frame += 1;

        // Keep launching for about 2.5s, then let sparks fade out.
        if (frame < 150 && frame - lastBurst > 8) {
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
    const ctx = canvas.getContext?.('2d');
    if (ctx) {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function chipButton(label, slug, active = false, color = null) {
    const activeClass = active ? 'active' : '';
    const bg = active ? `style="background:${color || '#0058b8'};color:#fff"` : '';
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

function safeColor(value) {
    return /^#[0-9a-f]{3,8}$/i.test(String(value || '')) ? value : '#64748b';
}
