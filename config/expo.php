<?php

return [
    'privacy_notice_version' => env('EXPO_PRIVACY_VERSION', '1.0'),
    'geocoder_user_agent' => env(
        'EXPO_GEOCODER_USER_AGENT',
        'ExpoVisitorMap/1.0 (booth check-in; contact: booth@example.com)'
    ),
    // Clean light vector style — fits the premium white booth UI.
    'map_style' => env('EXPO_MAP_STYLE', 'https://tiles.openfreemap.org/styles/liberty'),
];
