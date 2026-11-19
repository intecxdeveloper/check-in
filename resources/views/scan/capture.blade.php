<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Scan card · {{ config('expo.brand_name') }}</title>
    @include('partials.brand-meta')
    @vite(['resources/css/app.css', 'resources/js/card-capture.js'])
</head>
<body class="card-scan-body antialiased">
    <a href="#shutter" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to shutter</a>
    <div
        id="card-capture"
        class="card-scan"
        data-store="{{ $storeUrl }}"
        data-csrf="{{ csrf_token() }}"
    >
        <header class="card-scan-top">
            <img src="{{ asset(config('expo.brand_mark')) }}" alt="{{ config('expo.brand_name') }}" class="h-7 w-auto" width="128" height="44">
            <p>Hold the card in the frame</p>
        </header>

        <div class="card-scan-view">
            <video id="card-video" autoplay playsinline muted></video>
            <canvas id="card-canvas" hidden></canvas>
            <img id="card-preview" alt="" hidden>
            <div class="card-scan-frame" aria-hidden="true"></div>
        </div>

        <p id="card-scan-status" class="card-scan-status" role="status">Allow camera access to photograph the card.</p>

        <div class="card-scan-dock">
            <label class="card-scan-roll">
                <span class="sr-only">Choose a photo from the library</span>
                <input id="card-file" type="file" accept="image/*" capture="environment">
                Album
            </label>
            <button type="button" id="shutter" class="card-scan-shutter" aria-label="Take photo">
                <span></span>
            </button>
            <button type="button" id="card-retake" class="card-scan-roll" hidden>Retake</button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js" crossorigin="anonymous" defer></script>
</body>
</html>
