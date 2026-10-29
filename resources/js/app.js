import './bootstrap';
import { createExpoMap } from './expo-map';
import { createCheckInWizard } from './checkin-wizard';
import { avatarUrl } from './avatars';

const root = document.getElementById('expo-app');

if (root) {
    const maplibregl = window.maplibregl;

    if (root.dataset.mode === 'checkin') {
        const services = JSON.parse(root.dataset.services || '[]');
        const wizard = createCheckInWizard({
            root,
            maplibregl,
            services,
            lockOpen: true,
            onSuccess: async (payload) => {
                const successAvatar = root.querySelector('#success-avatar');
                if (successAvatar) {
                    const seed = payload.company?.properties?.name
                        || root.querySelector('[name="full_name"]')?.value
                        || 'friend';
                    successAvatar.src = avatarUrl(seed, 160);
                    successAvatar.alt = seed;
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
