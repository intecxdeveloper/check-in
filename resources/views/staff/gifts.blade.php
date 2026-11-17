@extends('layouts.staff')

@section('title', 'Gifts')

@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold tracking-tight text-navy">Gifts</h1>
        <p class="staff-num text-sm text-mute">{{ number_format($gifts->count()) }} {{ \Illuminate\Support\Str::plural('slice', $gifts->count()) }} on the live map wheel</p>
        <p class="mt-2 max-w-xl text-sm text-mute">Add booth prizes here. Reload the live map after you save so the wheel updates.</p>
    </div>
    @if ($gifts->isEmpty())
        <form method="POST" action="{{ route('staff.gifts.starter') }}">
            @csrf
            <button class="inline-flex min-h-10 items-center rounded-full border border-black/10 bg-white px-4 text-sm font-semibold hover:bg-black/[0.03]">Load starter gifts</button>
        </form>
    @endif
</div>

<form method="POST" action="{{ route('staff.gifts.store') }}" class="mb-6 rounded-2xl border border-black/[0.06] bg-white p-5">
    @csrf
    <h2 class="text-base font-bold text-navy">Add a gift</h2>
    <p class="mt-1 text-sm text-mute">Name is what staff see. Short name is the word on the wheel.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_8rem_5.5rem_auto] sm:items-end">
        <label class="block text-xs font-semibold text-mute">
            Gift name
            <input type="text" name="label" value="{{ old('label') }}" required maxlength="80" placeholder="NADI tote bag"
                   class="mt-1 min-h-11 w-full rounded-xl border border-black/10 bg-white px-3.5 text-base font-normal text-ink sm:text-sm">
        </label>
        <label class="block text-xs font-semibold text-mute">
            Wheel label
            <input type="text" name="short" value="{{ old('short') }}" maxlength="16" placeholder="Tote"
                   class="mt-1 min-h-11 w-full rounded-xl border border-black/10 bg-white px-3.5 text-base font-normal text-ink sm:text-sm">
        </label>
        <label class="block text-xs font-semibold text-mute">
            Colour
            <input type="color" name="color" value="{{ old('color', $nextColor) }}" required
                   class="mt-1 h-11 w-full cursor-pointer rounded-xl border border-black/10 bg-white p-1">
        </label>
        <button class="min-h-11 rounded-xl bg-navy px-5 text-sm font-semibold text-white hover:bg-navy-2">Add to wheel</button>
    </div>
    @error('label')
        <p class="mt-2 text-sm font-semibold text-[#c41e3a]">{{ $message }}</p>
    @enderror
    @error('short')
        <p class="mt-2 text-sm font-semibold text-[#c41e3a]">{{ $message }}</p>
    @enderror
    @error('color')
        <p class="mt-2 text-sm font-semibold text-[#c41e3a]">{{ $message }}</p>
    @enderror
</form>

<div class="space-y-3">
    @forelse ($gifts as $gift)
        <article class="rounded-2xl border border-black/[0.06] bg-white p-4 sm:p-5">
            <form method="POST" action="{{ route('staff.gifts.update', $gift) }}" class="grid gap-3 sm:grid-cols-[1fr_8rem_5.5rem_5rem_auto] sm:items-end">
                @csrf
                @method('PATCH')
                <label class="block text-xs font-semibold text-mute">
                    Gift name
                    <input type="text" name="label" value="{{ old('label', $gift->label) }}" required maxlength="80"
                           class="mt-1 min-h-11 w-full rounded-xl border border-black/10 bg-canvas px-3.5 text-base font-normal text-ink sm:text-sm">
                </label>
                <label class="block text-xs font-semibold text-mute">
                    Wheel label
                    <input type="text" name="short" value="{{ old('short', $gift->short) }}" maxlength="16"
                           class="mt-1 min-h-11 w-full rounded-xl border border-black/10 bg-canvas px-3.5 text-base font-normal text-ink sm:text-sm">
                </label>
                <label class="block text-xs font-semibold text-mute">
                    Colour
                    <input type="color" name="color" value="{{ old('color', $gift->color) }}" required
                           class="mt-1 h-11 w-full cursor-pointer rounded-xl border border-black/10 bg-canvas p-1">
                </label>
                <label class="block text-xs font-semibold text-mute">
                    Order
                    <input type="number" name="sort_order" value="{{ old('sort_order', $gift->sort_order) }}" min="1" max="99"
                           class="mt-1 min-h-11 w-full rounded-xl border border-black/10 bg-canvas px-3 text-base font-normal text-ink sm:text-sm">
                </label>
                <button class="min-h-11 rounded-xl bg-navy px-4 text-sm font-semibold text-white hover:bg-navy-2">Save</button>
            </form>
            <form method="POST" action="{{ route('staff.gifts.destroy', $gift) }}" class="mt-3"
                  onsubmit="return confirm('Remove this gift from the wheel?')">
                @csrf
                @method('DELETE')
                <button class="text-sm font-semibold text-[#c41e3a] hover:underline">Remove</button>
            </form>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-black/15 bg-white px-6 py-12 text-center">
            @include('partials.aiva', ['pose' => 'idle', 'size' => 64, 'class' => 'mx-auto mb-3'])
            <p class="font-semibold">No custom gifts yet</p>
            <p class="mt-1 text-sm text-mute">The map is using the default NADI prizes until you add your own, or load the starter set.</p>
        </div>
    @endforelse
</div>
@endsection
