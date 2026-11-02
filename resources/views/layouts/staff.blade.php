<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'Staff') · Expo Check-in</title>
    @vite(['resources/css/app.css'])
</head>
<body class="staff-body min-h-screen antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>
    <header class="sticky top-0 z-20 border-b border-black/[0.06] bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3">
            <a href="{{ route('staff.dashboard') }}" class="flex items-baseline gap-2">
                <span class="text-[0.95rem] font-bold tracking-tight">Visitor Desk</span>
                <span class="text-xs font-semibold text-mute">Staff</span>
            </a>
            @auth
                <nav class="staff-nav -mx-1 flex items-center gap-1 overflow-x-auto text-sm font-semibold" aria-label="Staff">
                    <a href="{{ route('staff.dashboard') }}" @if (request()->routeIs('staff.dashboard')) aria-current="page" @endif class="rounded-full px-3 py-1.5 hover:bg-black/[0.05]">Overview</a>
                    <a href="{{ route('staff.visitors') }}" @if (request()->routeIs('staff.visitors')) aria-current="page" @endif class="rounded-full px-3 py-1.5 hover:bg-black/[0.05]">Visitors</a>
                    <a href="{{ route('map') }}" target="_blank" rel="noopener" class="rounded-full px-3 py-1.5 hover:bg-black/[0.05]">Live map <span aria-hidden="true">↗</span><span class="sr-only">(opens in new tab)</span></a>
                    <form method="POST" action="{{ route('staff.logout') }}" class="ml-1">
                        @csrf
                        <button class="rounded-full px-3 py-1.5 text-mute hover:bg-black/[0.05] hover:text-ink">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>
    <main id="main" class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
        @if (session('status'))
            <div role="status" class="mb-5 rounded-xl border border-green/25 bg-green/10 px-4 py-3 text-sm font-medium text-[#1f7a37]">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
