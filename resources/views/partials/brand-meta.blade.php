@php($brand = config('expo.brand_name'))
<meta name="theme-color" content="#00254e">
<meta name="description" content="{{ $description ?? $brand.' visitor map: check in at the booth and see where everyone is from.' }}">
<link rel="icon" href="{{ asset('images/icon-32.png') }}" sizes="32x32" type="image/png">
<link rel="icon" href="{{ asset('images/icon-192.png') }}" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:title" content="{{ $ogTitle ?? $brand.' · Visitor Map' }}">
<meta property="og:description" content="{{ $description ?? 'Check in at the '.$brand.' booth and join the live Malaysia map.' }}">
<meta property="og:image" content="{{ asset('images/og-nadi-spatial.jpg') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
