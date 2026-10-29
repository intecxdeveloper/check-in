const AVATAR_COLORS = [
    'b6e3f4',
    'c0aede',
    'd1d4f9',
    'ffd5dc',
    'ffdfbf',
    'c1f4c5',
];

export const PRESET_AVATARS = [
    { seed: 'Aisha', label: 'Aisha' },
    { seed: 'Daniel', label: 'Daniel' },
    { seed: 'Nurul', label: 'Nurul' },
    { seed: 'Farid', label: 'Farid' },
    { seed: 'Mei', label: 'Mei' },
    { seed: 'Arif', label: 'Arif' },
    { seed: 'Priya', label: 'Priya' },
    { seed: 'Jason', label: 'Jason' },
];

export function avatarUrl(seed, size = 128) {
    const safeSeed = encodeURIComponent(String(seed || 'friend').trim() || 'friend');
    const backgrounds = AVATAR_COLORS.join(',');

    return `https://api.dicebear.com/9.x/avataaars/svg?seed=${safeSeed}&size=${size}&backgroundColor=${backgrounds}&radius=50`;
}

export function resolveAvatarUrl({ avatarUrl: customUrl, seed, name, size = 128 } = {}) {
    if (customUrl) {
        return customUrl;
    }

    return avatarUrl(seed || name || 'friend', size);
}

export function initials(name = '') {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (! parts.length) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}
