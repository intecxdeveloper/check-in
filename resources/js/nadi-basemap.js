const STORAGE_KEY = 'nadi-basemap';

const NADI = {
    land: '#173352',
    water: '#07111c',
    waterLine: '#1d4f7a',
    park: '#1a4036',
    parkEdge: '#245646',
    wood: '#16382f',
    grass: '#1c4538',
    wetland: '#123248',
    sand: '#3a3428',
    ice: '#1a3a52',
    residential: '#1d3d60',
    aeroway: '#1e4064',
    aerowayLine: '#3d6488',
    building: '#214668',
    buildingEdge: '#4d7094',
    building3d: '#275078',
    motorway: '#c41230',
    motorwayCasing: '#c5ccd4',
    primary: '#c5ccd4',
    primaryCasing: '#10283f',
    secondary: '#aeb6c0',
    secondaryCasing: '#12283f',
    street: '#c5ccd4',
    streetCasing: '#16324c',
    river: '#b7c3ce',
    rail: '#6a849c',
    railCasing: '#10283f',
    boundary: '#5c7c98',
    label: '#f7f8fa',
    waterLabel: '#c5d4e2',
    halo: '#07111c',
    roadArea: '#1e4064',
};

let themedStylePromise = null;

export function readBasemapId() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'nadi' ? 'nadi' : 'liberty';
    } catch (_) {
        return 'liberty';
    }
}

export function storeBasemapId(id) {
    try {
        localStorage.setItem(STORAGE_KEY, id === 'nadi' ? 'nadi' : 'liberty');
    } catch (_) {
        // Private mode can block storage; the choice still applies this visit.
    }
}

export function markBasemapButtons(root, id) {
    root.querySelectorAll('[data-basemap]').forEach((button) => {
        const on = button.dataset.basemap === id;
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
}

export function skyForBasemap(id) {
    if (id === 'nadi') {
        return {
            'sky-color': '#07111c',
            'sky-horizon-blend': 0.2,
            'horizon-color': '#173352',
            'horizon-fog-blend': 0.05,
            'fog-color': '#07111c',
            'fog-ground-blend': 0,
        };
    }

    return {
        'sky-color': '#b9dcff',
        'sky-horizon-blend': 0.2,
        'horizon-color': '#f5f7fa',
        'horizon-fog-blend': 0.05,
        'fog-color': '#ffffff',
        'fog-ground-blend': 0,
    };
}

const PENINSULA_TRIP_SECONDS = 28;

// North–south expressway spines. West stays on the PLUS corridor.
// East leaves Perlis across the East–West highway, then follows the east coast to Johor.
const PENINSULA_ROUTES = [
    {
        name: 'west',
        line: [
            [100.321, 6.662],
            [100.199, 6.441],
            [100.429, 6.430],
            [100.368, 6.124],
            [100.488, 5.647],
            [100.364, 5.399],
            [100.741, 4.851],
            [100.942, 4.773],
            [101.090, 4.597],
            [101.261, 4.198],
            [101.519, 3.681],
            [101.577, 3.322],
            [101.687, 3.139],
            [101.797, 2.803],
            [101.938, 2.726],
            [102.070, 2.550],
            [102.283, 2.266],
            [102.545, 2.267],
            [103.066, 2.015],
            [103.180, 1.915],
            [103.657, 1.537],
            [103.757, 1.465],
        ],
    },
    {
        name: 'east',
        line: [
            [100.321, 6.662],
            [100.429, 6.430],
            [100.368, 6.124],
            [100.474, 5.817],
            [100.647, 5.598],
            [101.131, 5.417],
            [101.843, 5.701],
            [102.148, 5.810],
            [102.238, 6.125],
            [102.407, 5.836],
            [103.141, 5.330],
            [103.416, 4.756],
            [103.326, 3.817],
            [103.389, 3.492],
            [103.487, 2.812],
            [103.841, 2.431],
            [103.899, 1.729],
            [103.757, 1.465],
        ],
    },
];

let roadPulseFrame = 0;
let roadPulseMap = null;
let roadPulseNodes = [];
let roadPulseRunners = [];

function stopRoadFlow() {
    if (roadPulseFrame) {
        cancelAnimationFrame(roadPulseFrame);
        roadPulseFrame = 0;
    }
    roadPulseNodes.forEach((node) => node.remove());
    roadPulseNodes = [];
    roadPulseRunners = [];
    roadPulseMap = null;
}

function kilometresBetween(from, to) {
    const earth = 6371;
    const lat1 = from[1] * Math.PI / 180;
    const lat2 = to[1] * Math.PI / 180;
    const dLat = lat2 - lat1;
    const dLng = (to[0] - from[0]) * Math.PI / 180;
    const arc = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

    return 2 * earth * Math.asin(Math.min(1, Math.sqrt(arc)));
}

function measureRoute(line) {
    const cumulative = [0];
    for (let index = 1; index < line.length; index += 1) {
        cumulative.push(cumulative[index - 1] + kilometresBetween(line[index - 1], line[index]));
    }

    return { line, cumulative, total: cumulative[cumulative.length - 1] || 1 };
}

function coordinateAlongRoute(route, progress) {
    const target = Math.min(route.total, Math.max(0, progress) * route.total);
    let index = 1;
    while (index < route.cumulative.length && route.cumulative[index] < target) {
        index += 1;
    }
    const start = route.cumulative[index - 1];
    const end = route.cumulative[index];
    const mix = end === start ? 0 : (target - start) / (end - start);
    const from = route.line[index - 1];
    const to = route.line[index];

    return [
        from[0] + (to[0] - from[0]) * mix,
        from[1] + (to[1] - from[1]) * mix,
    ];
}

const PENINSULA_ROUTE_METRICS = PENINSULA_ROUTES.map((route) => ({
    name: route.name,
    ...measureRoute(route.line),
}));

export function syncRoadFlow(map, basemapId) {
    stopRoadFlow();
    if (! map || basemapId !== 'nadi') {
        return;
    }

    const container = map.getCanvasContainer();
    roadPulseMap = map;
    roadPulseNodes = PENINSULA_ROUTE_METRICS.map((route, index) => {
        const node = document.createElement('div');
        node.className = 'road-pulse';
        node.dataset.route = route.name;
        node.hidden = true;
        node.innerHTML = '<span class="road-pulse-ring"></span><span class="road-pulse-dot"></span>';
        node.querySelector('.road-pulse-ring').style.animationDelay = `${index * 0.45}s`;
        container.appendChild(node);

        return node;
    });
    roadPulseRunners = PENINSULA_ROUTE_METRICS.map((route) => ({ route, progress: 0 }));

    let last = performance.now();
    const tick = (now) => {
        roadPulseFrame = requestAnimationFrame(tick);
        if (! roadPulseMap || document.hidden) {
            last = now;

            return;
        }

        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        const width = roadPulseMap.getContainer().clientWidth;
        const height = roadPulseMap.getContainer().clientHeight;

        roadPulseRunners.forEach((runner, index) => {
            const node = roadPulseNodes[index];
            runner.progress = (runner.progress + dt / PENINSULA_TRIP_SECONDS) % 1;
            const coord = coordinateAlongRoute(runner.route, runner.progress);
            const point = roadPulseMap.project(coord);
            const onScreen = point.x > -30 && point.y > -30 && point.x < width + 30 && point.y < height + 30;
            node.hidden = ! onScreen;
            node.style.transform = `translate3d(${point.x}px, ${point.y}px, 0)`;
        });
    };
    roadPulseFrame = requestAnimationFrame(tick);
}

function setPaint(layer, key, value) {
    layer.paint = layer.paint || {};
    layer.paint[key] = value;
}

function paintRoad(layer) {
    const id = layer.id;
    const casing = id.includes('casing');
    if (id.includes('rail')) {
        setPaint(layer, 'line-color', casing ? NADI.railCasing : NADI.rail);
        return;
    }
    if (id.includes('motorway')) {
        setPaint(layer, 'line-color', casing ? NADI.motorwayCasing : NADI.motorway);
        return;
    }
    if (id.includes('trunk_primary')) {
        setPaint(layer, 'line-color', casing ? NADI.primaryCasing : NADI.primary);
        if (! casing) {
            setPaint(layer, 'line-width', ['interpolate', ['exponential', 1.2], ['zoom'], 4, 0.5, 7, 1.15, 12, 4, 20, 18]);
        }
        return;
    }
    if (id.includes('secondary_tertiary')) {
        setPaint(layer, 'line-color', casing ? NADI.secondaryCasing : NADI.secondary);
        if (! casing) {
            setPaint(layer, 'line-width', ['interpolate', ['exponential', 1.2], ['zoom'], 6, 0.4, 8, 0.95, 14, 3, 20, 13]);
        }
        return;
    }
    setPaint(layer, 'line-color', casing ? NADI.streetCasing : NADI.street);
}

function paintLabel(layer, color) {
    setPaint(layer, 'text-color', color);
    setPaint(layer, 'text-halo-color', NADI.halo);
}

export function applyCompanyTheme(style) {
    const next = structuredClone(style);

    for (const layer of next.layers) {
        const id = layer.id;

        if (id === 'background') {
            setPaint(layer, 'background-color', NADI.land);
            continue;
        }
        if (id === 'natural_earth') {
            setPaint(layer, 'raster-opacity', 0);
            continue;
        }
        if (id === 'water') {
            setPaint(layer, 'fill-color', NADI.water);
            continue;
        }
        if (id.startsWith('waterway')) {
            if (layer.type === 'line') {
                setPaint(layer, 'line-color', NADI.river);
                setPaint(layer, 'line-opacity', 0.92);
                if (id === 'waterway_river') {
                    setPaint(layer, 'line-width', ['interpolate', ['exponential', 1.2], ['zoom'], 4, 0.6, 7, 1.2, 12, 2.4, 20, 6]);
                }
            }
            if (layer.type === 'symbol') {
                paintLabel(layer, NADI.waterLabel);
            }
            continue;
        }
        if (id.startsWith('water_name')) {
            paintLabel(layer, NADI.waterLabel);
            continue;
        }
        if (id === 'park' || id === 'park_outline') {
            if (layer.type === 'fill') {
                setPaint(layer, 'fill-color', NADI.park);
                setPaint(layer, 'fill-outline-color', NADI.parkEdge);
            }
            if (layer.type === 'line') {
                setPaint(layer, 'line-color', NADI.parkEdge);
            }
            continue;
        }
        if (id === 'landcover_wood') {
            setPaint(layer, 'fill-color', NADI.wood);
            continue;
        }
        if (id === 'landcover_grass' || id === 'landuse_pitch' || id === 'landuse_cemetery') {
            setPaint(layer, 'fill-color', NADI.grass);
            continue;
        }
        if (id === 'landcover_wetland') {
            setPaint(layer, 'fill-color', NADI.wetland);
            continue;
        }
        if (id === 'landcover_sand') {
            setPaint(layer, 'fill-color', NADI.sand);
            continue;
        }
        if (id === 'landcover_ice') {
            setPaint(layer, 'fill-color', NADI.ice);
            continue;
        }
        if (id === 'landuse_residential' || id === 'landuse_hospital' || id === 'landuse_school' || id === 'landuse_track') {
            setPaint(layer, 'fill-color', NADI.residential);
            continue;
        }
        if (id.startsWith('aeroway')) {
            if (layer.type === 'fill') {
                setPaint(layer, 'fill-color', NADI.aeroway);
            }
            if (layer.type === 'line') {
                setPaint(layer, 'line-color', NADI.aerowayLine);
            }
            continue;
        }
        if (id === 'building') {
            setPaint(layer, 'fill-color', NADI.building);
            setPaint(layer, 'fill-outline-color', NADI.buildingEdge);
            continue;
        }
        if (id === 'building-3d') {
            setPaint(layer, 'fill-extrusion-color', NADI.building3d);
            continue;
        }
        if (id.startsWith('boundary')) {
            setPaint(layer, 'line-color', NADI.boundary);
            continue;
        }
        if (id === 'road_area_pattern') {
            setPaint(layer, 'fill-color', NADI.roadArea);
            continue;
        }
        if (layer.type === 'line' && (id.startsWith('road_') || id.startsWith('tunnel_') || id.startsWith('bridge_'))) {
            paintRoad(layer);
            continue;
        }
        if (id.startsWith('highway-shield') || id.startsWith('road_shield')) {
            layer.layout = layer.layout || {};
            layer.layout.visibility = 'none';
            continue;
        }
        if (layer.type === 'symbol' && (
            id.startsWith('label_')
            || id.startsWith('highway-name')
            || id.startsWith('poi_')
            || id === 'airport'
        )) {
            paintLabel(layer, NADI.label);
        }
    }

    return next;
}

export async function resolveBasemapStyle(id, libertyUrl) {
    if (id !== 'nadi') {
        return libertyUrl;
    }

    if (! themedStylePromise) {
        themedStylePromise = fetch(libertyUrl)
            .then((response) => {
                if (! response.ok) {
                    throw new Error('Map style failed to load');
                }

                return response.json();
            })
            .then((style) => applyCompanyTheme(style))
            .catch((error) => {
                themedStylePromise = null;
                throw error;
            });
    }

    return structuredClone(await themedStylePromise);
}
