import './bootstrap';
import { createExpoMap } from './expo-map';
import { createCheckInWizard } from './checkin-wizard';

const root = document.getElementById('expo-app');

if (root) {
    const maplibregl = window.maplibregl;

    if (root.dataset.mode === 'checkin') {
        let services = [];
        try {
            services = JSON.parse(root.dataset.services || '[]');
        } catch (_) {
            services = [];
        }

        const wizard = createCheckInWizard({
            root,
            maplibregl,
            services: Array.isArray(services) ? services : [],
            lockOpen: true,
            onSuccess: async (payload) => {
                const successAvatar = root.querySelector('#success-avatar');
                if (successAvatar && payload.avatar_url) {
                    successAvatar.src = payload.avatar_url;
                }
            },
        });

        wizard.open();
    } else {
        if (! maplibregl) {
            console.error('MapLibre failed to load from CDN.');
            root.dataset.mapFailed = '1';
        }

        createExpoMap(root, maplibregl);
    }
}
