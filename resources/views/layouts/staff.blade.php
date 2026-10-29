<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Staff') · Expo Check-in</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Staff</p>
                <h1 class="text-lg font-semibold">Expo Visitor Desk</h1>
            </div>
            @auth
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('staff.dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Dashboard</a>
                    <a href="{{ route('staff.visitors') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Visitors</a>
                    <a href="{{ route('map') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100" target="_blank">Map</a>
                    <form method="POST" action="{{ route('staff.logout') }}">
                        @csrf
                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-white">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
