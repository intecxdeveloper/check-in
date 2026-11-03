<?php

return [
    'brand_name' => env('EXPO_BRAND_NAME', 'NADI Spatial'),
    'brand_logo' => 'images/nadi-spatial-logo.png',
    'brand_mark' => 'images/nadi-spatial-mark.png',

    'privacy_notice_version' => env('EXPO_PRIVACY_VERSION', '1.0'),
    'geocoder_user_agent' => env(
        'EXPO_GEOCODER_USER_AGENT',
        'ExpoVisitorMap/1.0 (booth check-in)'
    ),
    // OSM vector style (OpenFreeMap). Includes building-3d extrusions.
    'map_style' => env('EXPO_MAP_STYLE', 'https://tiles.openfreemap.org/styles/liberty'),

    // Booth camera: pitched OSM view with 3D buildings + terrain.
    'map_pitch' => (float) env('EXPO_MAP_PITCH', 48),
    'map_max_pitch' => (float) env('EXPO_MAP_MAX_PITCH', 68),
    'map_bearing' => (float) env('EXPO_MAP_BEARING', -12),
    'map_terrain' => filter_var(env('EXPO_MAP_TERRAIN', true), FILTER_VALIDATE_BOOL),
    'map_terrain_exaggeration' => (float) env('EXPO_MAP_TERRAIN_EXAGGERATION', 1.25),
    // Geographic centroid of Malaysia; used until visitor pins can replace it.
    'map_center_lng' => (float) env('EXPO_MAP_CENTER_LNG', 101.9758),
    'map_center_lat' => (float) env('EXPO_MAP_CENTER_LAT', 4.2105),
    'map_start_zoom' => (float) env('EXPO_MAP_START_ZOOM', 5.6),
    'map_max_zoom' => (float) env('EXPO_MAP_MAX_ZOOM', 16.5),

    // OpenCorporates needs a token (free tier). Without it, search uses local directory only.
    'company_search_opencorporates' => env('EXPO_COMPANY_SEARCH_OPENCORPORATES', true),
    'opencorporates_api_token' => env('OPENCORPORATES_API_TOKEN'),
];
