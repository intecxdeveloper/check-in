const AVATAR_COLORS = [
    'b6e3f4',
    'c0aede',
    'd1d4f9',
    'ffd5dc',
    'ffdfbf',
    'c1f4c5',
];

/** Soft booth vibe, but dress-code locked to blazers / collars. */
const CORPORATE_LOOK = {
    clothing: 'blazerAndShirt,blazerAndSweater,collarAndSweater',
    clothingColor: '262e33,3c4f5c,5199e4,25557c,65c9ff,929598',
    accessories: 'prescription01,prescription02,round,wayfarers',
    accessoriesProbability: '28',
    facialHairProbability: '12',
    mouth: 'smile,default,twinkle',
    eyebrows: 'default,defaultNatural,flatNatural',
};

export const PRESET_AVATARS = [
    { seed: 'AishaCorp', label: 'Aisha', clothing: 'blazerAndShirt', clothingColor: '25557c' },
    { seed: 'DanielCorp', label: 'Daniel', clothing: 'blazerAndSweater', clothingColor: '262e33' },
    { seed: 'NurulCorp', label: 'Nurul', clothing: 'collarAndSweater', clothingColor: '5199e4' },
    { seed: 'FaridCorp', label: 'Farid', clothing: 'blazerAndShirt', clothingColor: '3c4f5c' },
    { seed: 'MeiCorp', label: 'Mei', clothing: 'blazerAndSweater', clothingColor: '65c9ff' },
    { seed: 'ArifCorp', label: 'Arif', clothing: 'blazerAndShirt', clothingColor: '262e33' },
    { seed: 'PriyaCorp', label: 'Priya', clothing: 'collarAndSweater', clothingColor: '25557c' },
    { seed: 'JasonCorp', label: 'Jason', clothing: 'blazerAndSweater', clothingColor: '929598' },
];

export function avatarUrl(seed, size = 128, extras = {}) {
    const safeSeed = String(seed || 'friend').trim() || 'friend';
    const params = new URLSearchParams({
        seed: safeSeed,
        size: String(size),
        backgroundColor: AVATAR_COLORS.join(','),
        radius: '50',
        ...CORPORATE_LOOK,
        ...Object.fromEntries(
            Object.entries(extras).filter(([, value]) => value != null && value !== '')
        ),
    });

    return `https://api.dicebear.com/9.x/avataaars/svg?${params.toString()}`;
}

export function presetAvatarUrl(preset, size = 128) {
    return avatarUrl(preset.seed, size, {
        clothing: preset.clothing,
        clothingColor: preset.clothingColor,
    });
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
