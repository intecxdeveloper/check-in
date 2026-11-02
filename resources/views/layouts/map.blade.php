<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('expo.brand_name').' · Visitor Map' }}</title>
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" href="{{ asset(config('expo.brand_mark')) }}" type="image/png">
    <link rel="preconnect" href="https://tiles.openfreemap.org" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link href="https://unpkg.com/maplibre-gl@5.9.0/dist/maplibre-gl.css" rel="stylesheet" integrity="sha384-gNYNsUmuZqDYiT3gbirWTV5K7rt71RoveS/yXAaU09d4ZUmeDVTD3XoqB6uJAIFR" crossorigin="anonymous">
    <script src="https://unpkg.com/maplibre-gl@5.9.0/dist/maplibre-gl.js" integrity="sha384-81ppUWZZO+pveTPk4qU9cZuScEFULHfuOMHVJHFZ7xVBlWQTG1zsPrVIMGAl1WMb" crossorigin="anonymous" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js" integrity="sha384-0RsG1yo/crf/1Qc14sho26SXXOTngNCjgJw7fuvXBt9W/OChF/Ijx+aUuBDqQwEk" crossorigin="anonymous" defer></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="map-body antialiased">
    @yield('content')
</body>
</html>
