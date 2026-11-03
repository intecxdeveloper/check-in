import './bootstrap';
import { createExpoMap } from './expo-map';

const root = document.getElementById('expo-app');

if (root) {
    const maplibregl = window.maplibregl;

    if (! maplibregl) {
        console.error('MapLibre failed to load from CDN.');
        root.dataset.mapFailed = '1';
    }

    createExpoMap(root, maplibregl);
}
