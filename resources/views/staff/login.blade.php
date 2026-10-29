@extends('layouts.staff')

@section('title', 'Login')

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold">Staff login</h2>
    <p class="mt-1 text-sm text-slate-600">Access visitor records, lead status, and CSV export.</p>

    <form method="POST" action="{{ route('staff.login.submit') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Password</span>
            <input type="password" name="password" required
                   class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1">
            Remember me
        </label>
        @error('email')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
        <button class="w-full rounded-xl bg-slate-900 px-4 py-2.5 font-medium text-white">Sign in</button>
    </form>
</div>
@endsection
