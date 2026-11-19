@extends('layouts.staff')

@section('title', 'Cards')

@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold tracking-tight text-navy">Business cards</h1>
        <p class="mt-1 max-w-xl text-sm text-mute">Scan the QR with a phone, photograph a visitor’s card, and it lands in this gallery. Keep this page open: it reads the text from each photo and fills the table below by itself.</p>
    </div>
    <form method="POST" action="{{ route('staff.cards.link') }}">
        @csrf
        <button class="inline-flex min-h-10 items-center rounded-full border border-black/10 bg-white px-4 text-sm font-semibold hover:bg-black/[0.03]">New QR</button>
    </form>
</div>

<div class="grid gap-6 lg:grid-cols-[16.5rem_minmax(0,1fr)]">
    <aside class="rounded-2xl border border-black/[0.06] bg-white p-5">
        <h2 class="text-base font-bold text-navy">Phone camera</h2>
        <p class="mt-1 text-xs text-mute">Point the phone at this code. Keep this page open while you scan.</p>
        <div class="card-qr-frame mt-4">
            <canvas id="card-qr" width="220" height="220" aria-label="QR code for the phone camera"></canvas>
        </div>
        <p class="mt-3 break-all font-mono text-[11px] leading-relaxed text-mute">{{ $scanUrl }}</p>
        <p class="mt-2 text-xs text-mute">Link expires <time datetime="{{ $scanExpiresAt?->toIso8601String() }}">{{ $scanExpiresAt?->timezone(config('app.timezone'))->format('g:i A') }}</time>.</p>
        @if ($phoneCannotReach)
            <p class="mt-3 rounded-xl border border-orange/30 bg-orange/10 px-3 py-2 text-xs font-medium text-ink">This QR is a local address. Set EXPO_PUBLIC_URL to the public https domain so a phone can open it.</p>
        @endif
    </aside>

    <section class="min-w-0 rounded-2xl border border-black/[0.06] bg-white p-4 sm:p-5" aria-labelledby="gallery-heading">
        <div class="flex items-center justify-between gap-3">
            <h2 id="gallery-heading" class="text-base font-bold text-navy">Gallery</h2>
            <p id="card-gallery-status" class="text-xs font-semibold text-mute" aria-live="polite">Waiting for the phone</p>
        </div>
        <div
            id="card-gallery"
            class="card-coverflow"
            data-feed="{{ route('staff.cards.feed') }}"
            data-csrf="{{ csrf_token() }}"
            data-scan-url="{{ $scanUrl }}"
            data-cards='@json($cards->map->toGalleryArray()->values())'
        >
            <div class="card-coverflow-stage" data-stage>
                <p class="card-coverflow-empty" data-empty>
                    @include('partials.aiva', ['pose' => 'idle', 'size' => 56, 'class' => 'mx-auto mb-3'])
                    Scan the QR, then photograph a card. New scans appear here.
                </p>
            </div>
            <div class="card-coverflow-controls">
                <button type="button" class="card-coverflow-nav" data-prev aria-label="Previous card">‹</button>
                <p class="card-coverflow-count" data-count></p>
                <button type="button" class="card-coverflow-nav" data-next aria-label="Next card">›</button>
            </div>
        </div>
        <div id="card-gallery-caption" class="card-coverflow-caption" hidden></div>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-black/[0.06] bg-white p-5" aria-labelledby="card-table-heading">
    <h2 id="card-table-heading" class="text-base font-bold text-navy">Recorded details</h2>
    <p class="mt-1 text-sm text-mute">Correct anything OCR missed. These rows stay on the staff desk; they are not shown on the live map.</p>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[52rem] border-collapse text-sm">
            <thead>
                <tr class="border-b border-black/[0.06] text-left text-xs font-semibold text-mute">
                    <th class="py-2 pr-3">Card</th>
                    <th class="py-2 pr-3">Name</th>
                    <th class="py-2 pr-3">Title</th>
                    <th class="py-2 pr-3">Company</th>
                    <th class="py-2 pr-3">Email</th>
                    <th class="py-2 pr-3">Phone</th>
                    <th class="py-2 pr-3">Notes</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody id="card-table-body">
                @forelse ($tableCards as $card)
                    <tr class="border-b border-black/[0.04] align-top" data-card-row="{{ $card->id }}">
                        <td class="py-3 pr-3">
                            @if ($card->imageUrl())
                                <img src="{{ $card->imageUrl() }}" alt="" class="h-14 w-24 rounded-lg object-cover ring-1 ring-black/5">
                            @endif
                        </td>
                        <td colspan="7" class="py-3">
                            <form method="POST" action="{{ route('staff.cards.update', $card) }}" class="grid grid-cols-6 gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="full_name" value="{{ old('full_name', $card->full_name) }}" placeholder="Name" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="text" name="job_title" value="{{ old('job_title', $card->job_title) }}" placeholder="Title" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="text" name="company_name" value="{{ old('company_name', $card->company_name) }}" placeholder="Company" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="text" name="email" value="{{ old('email', $card->email) }}" placeholder="Email" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="text" name="phone" value="{{ old('phone', $card->phone) }}" placeholder="Phone" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="text" name="notes" value="{{ old('notes', $card->notes) }}" placeholder="Notes" class="min-h-10 rounded-lg border border-black/10 bg-canvas px-2.5 text-sm">
                                <input type="hidden" name="website" value="{{ old('website', $card->website) }}">
                                <input type="hidden" name="address" value="{{ old('address', $card->address) }}">
                                <div class="col-span-6 flex flex-wrap items-center justify-between gap-2">
                                    <p class="max-w-xl truncate text-xs text-mute">{{ $card->website ?: $card->address ?: $card->created_at?->diffForHumans() }}</p>
                                    <button class="min-h-9 rounded-full bg-navy px-3 text-xs font-semibold text-white hover:bg-navy-2">Save</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('staff.cards.destroy', $card) }}" class="mt-2" onsubmit="return confirm('Remove this business card?')">
                                @csrf
                                @method('DELETE')
                                <button class="min-h-9 rounded-full px-3 text-xs font-semibold text-mute hover:bg-black/[0.05] hover:text-ink">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-10 text-center text-sm text-mute">No cards recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tableCards->links() }}</div>
</section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js" integrity="sha384-0RsG1yo/crf/1Qc14sho26SXXOTngNCjgJw7fuvXBt9W/OChF/Ijx+aUuBDqQwEk" crossorigin="anonymous" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js" integrity="sha384-GJqSu7vueQ9qN0E9yLPb3Wtpd7OrgK8KmYzC8T1IysG1bcvxvIO4qtYR/D3A991F" crossorigin="anonymous" defer></script>
    @vite(['resources/js/card-gallery.js'])
@endpush
