import './bootstrap';
import { createCheckInWizard } from './checkin-wizard';
import { prefetchMapLibre } from './maplibre';

const root = document.getElementById('expo-app');

if (root) {
    let services = [];
    try {
        services = JSON.parse(root.dataset.services || '[]');
    } catch (_) {
        services = [];
    }

    const wizard = createCheckInWizard({
        root,
        maplibregl: window.maplibregl,
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
    prefetchMapLibre();
}
