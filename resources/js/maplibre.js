const MAPLIBRE_CSS = 'https://unpkg.com/maplibre-gl@5.9.0/dist/maplibre-gl.css';
const MAPLIBRE_JS = 'https://unpkg.com/maplibre-gl@5.9.0/dist/maplibre-gl.js';
const MAPLIBRE_CSS_INTEGRITY = 'sha384-gNYNsUmuZqDYiT3gbirWTV5K7rt71RoveS/yXAaU09d4ZUmeDVTD3XoqB6uJAIFR';
const MAPLIBRE_JS_INTEGRITY = 'sha384-81ppUWZZO+pveTPk4qU9cZuScEFULHfuOMHVJHFZ7xVBlWQTG1zsPrVIMGAl1WMb';

function injectStylesheet(href, integrity) {
    if (document.querySelector(`link[href="${href}"]`)) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.integrity = integrity;
        link.crossOrigin = 'anonymous';
        link.onload = () => resolve();
        link.onerror = () => reject(new Error('Map styles failed to load.'));
        document.head.appendChild(link);
    });
}

function injectScript(src, integrity) {
    if (window.maplibregl) {
        return Promise.resolve();
    }

    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
        return new Promise((resolve, reject) => {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Map library failed to load.')), { once: true });
        });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.integrity = integrity;
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Map library failed to load.'));
        document.head.appendChild(script);
    });
}

export function prefetchMapLibre() {
    if (window.maplibregl || document.querySelector(`link[rel="prefetch"][href="${MAPLIBRE_JS}"]`)) {
        return;
    }

    const idle = window.requestIdleCallback || ((callback) => setTimeout(callback, 900));
    idle(() => {
        const css = document.createElement('link');
        css.rel = 'prefetch';
        css.as = 'style';
        css.href = MAPLIBRE_CSS;
        css.crossOrigin = 'anonymous';
        document.head.appendChild(css);

        const js = document.createElement('link');
        js.rel = 'prefetch';
        js.as = 'script';
        js.href = MAPLIBRE_JS;
        js.crossOrigin = 'anonymous';
        document.head.appendChild(js);
    });
}

export async function loadMapLibre() {
    if (window.maplibregl) {
        return window.maplibregl;
    }

    if (! window.__mapLibreLoader) {
        window.__mapLibreLoader = Promise.all([
            injectStylesheet(MAPLIBRE_CSS, MAPLIBRE_CSS_INTEGRITY),
            injectScript(MAPLIBRE_JS, MAPLIBRE_JS_INTEGRITY),
        ]).then(() => window.maplibregl);
    }

    return window.__mapLibreLoader;
}
