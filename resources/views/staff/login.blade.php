@extends('layouts.staff')

@section('title', 'Sign in')

@section('content')
<div class="mx-auto mt-4 max-w-sm sm:mt-12">
    <h1 class="text-2xl font-bold tracking-tight">Sign in to the visitor desk</h1>
    <p class="mt-1 text-sm text-mute">Staff only. Visitor records and exports stay behind this login.</p>

    <form method="POST" action="{{ route('staff.login.submit') }}" class="mt-6 space-y-4 rounded-2xl border border-black/[0.06] bg-white p-5 sm:p-6" novalidate>
        @csrf
        <label class="block text-sm font-semibold">
            Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"
                   @error('email') aria-invalid="true" aria-describedby="login-error" @enderror
                   class="mt-1.5 min-h-11 w-full rounded-xl border border-black/10 px-3.5 text-base font-normal">
        </label>
        <label class="block text-sm font-semibold">
            Password
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 min-h-11 w-full rounded-xl border border-black/10 px-3.5 text-base font-normal">
        </label>
        <label class="flex items-center gap-2 text-sm text-mute">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-ink">
            Keep me signed in on this device
        </label>
        @error('email')
            <p id="login-error" role="alert" class="rounded-lg bg-pink/10 px-3 py-2 text-sm font-medium text-[#c41e3a]">{{ $message }}</p>
        @enderror
        <button class="min-h-11 w-full rounded-xl bg-ink px-4 text-sm font-semibold text-white hover:bg-black">Sign in</button>
    </form>
</div>
@endsection
