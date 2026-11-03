@extends('layouts.staff')

@section('title', 'Visitors')

@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold tracking-tight text-navy">Visitors</h1>
        <p class="staff-num text-sm text-mute">{{ number_format($visitors->total()) }} {{ \Illuminate\Support\Str::plural('record', $visitors->total()) }}@if ($filters['q'] || $filters['status'] || $filters['messages']) matching your filters @endif · {{ number_format($liveMessages) }} live map {{ \Illuminate\Support\Str::plural('message', $liveMessages) }}</p>
        <p class="mt-2 max-w-xl text-sm text-mute">Status is NADI's follow-up with this visitor, not their company.</p>
    </div>
    <a href="{{ route('staff.export', request()->query()) }}"
       class="inline-flex min-h-10 items-center rounded-full border border-black/10 bg-white px-4 text-sm font-semibold hover:bg-black/[0.03]">
        Export CSV
    </a>
</div>

<form method="GET" role="search" class="mb-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
    <label class="sr-only" for="visitor-q">Search visitors</label>
    <input id="visitor-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, email, company or message"
           class="min-h-11 flex-1 rounded-xl border border-black/10 bg-white px-3.5 text-base sm:text-sm">
    <div class="flex flex-wrap gap-2">
        <label class="sr-only" for="visitor-status">Lead status</label>
        <select id="visitor-status" name="status" class="min-h-11 flex-1 rounded-xl border border-black/10 bg-white px-3 text-base sm:text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ \App\Models\Lead::label($status) }}</option>
            @endforeach
        </select>
        <label class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-black/10 bg-white px-3 text-sm font-semibold">
            <input type="checkbox" name="messages" value="1" class="size-4 accent-navy" @checked($filters['messages'])>
            Map messages
        </label>
        <button class="min-h-11 rounded-xl bg-navy px-5 text-sm font-semibold text-white">Search</button>
        @if ($filters['q'] || $filters['status'] || $filters['messages'])
            <a href="{{ route('staff.visitors') }}" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-mute hover:text-ink">Clear</a>
        @endif
    </div>
</form>

<div class="space-y-3">
    @forelse ($visitors as $visitor)
        <article class="rounded-2xl border border-black/[0.06] bg-white p-5" aria-labelledby="visitor-{{ $visitor->id }}">
            <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="visitor-{{ $visitor->id }}" class="text-base font-bold">{{ $visitor->full_name }}</h2>
                        @if ($visitor->lead)
                            <span class="staff-pill" data-status="{{ $visitor->lead->status }}">{{ \App\Models\Lead::label($visitor->lead->status) }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-mute">
                        {{ collect([$visitor->job_title, $visitor->company?->name])->filter()->implode(' · ') ?: 'No company' }}
                        · <time datetime="{{ $visitor->created_at?->toIso8601String() }}">{{ $visitor->created_at?->diffForHumans() }}</time>
                    </p>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold text-mute">Claim code</dt><dd class="font-mono font-bold tracking-wide">{{ $visitor->claimCode() }}</dd></div>
                        <div><dt class="text-xs font-semibold text-mute">Consent</dt><dd>{{ $visitor->company?->public_display_enabled ? 'Shown on map' : 'Hidden from map' }} · {{ $visitor->follow_up_consent_at ? 'Follow-up OK' : 'No follow-up' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-mute">Email</dt><dd class="break-all">@if ($visitor->email)<a class="hover:underline" href="mailto:{{ $visitor->email }}">{{ $visitor->email }}</a>@else — @endif</dd></div>
                        <div><dt class="text-xs font-semibold text-mute">Phone</dt><dd>@if ($visitor->phone)<a class="hover:underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $visitor->phone) }}">{{ $visitor->phone }}</a>@else — @endif</dd></div>
                        <div><dt class="text-xs font-semibold text-mute">Location</dt><dd>{{ $visitor->company?->locationLabel() ?: '—' }}</dd></div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-mute">Map message</dt>
                            <dd class="mt-1">
                                @if ($visitor->hasPublicMessage())
                                    <blockquote class="rounded-lg bg-canvas px-3 py-2 text-sm">“{{ $visitor->display_message }}”</blockquote>
                                    <form method="POST" action="{{ route('staff.visitors.message.destroy', $visitor) }}" class="mt-2"
                                          onsubmit="return confirm('Remove this message from the public map? The visitor record stays.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm font-semibold text-[#c41e3a] hover:underline">Remove from map</button>
                                    </form>
                                @elseif ($visitor->display_message_removed_at)
                                    <span class="text-mute">Removed from the map {{ $visitor->display_message_removed_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-mute">None</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if (($visitor->company?->services ?? collect())->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-1.5" aria-label="Interests">
                            @foreach ($visitor->company->services as $service)
                                <li class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    style="background: {{ $service->color }}24">{{ $service->name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($visitor->lead)
                    <form method="POST" action="{{ route('staff.leads.update', $visitor->lead) }}" class="space-y-3 rounded-xl bg-canvas p-4">
                        @csrf
                        @method('PATCH')
                        <p class="text-xs text-mute">NADI follow-up only — this does not change their company on the map.</p>
                        <label class="block text-xs font-semibold text-mute">
                            Status
                            <select name="status" class="mt-1 min-h-10 w-full rounded-lg border border-black/10 bg-white px-2.5 text-base font-normal text-ink sm:text-sm">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($visitor->lead->status === $status) title="{{ \App\Models\Lead::meaning($status) }}">{{ \App\Models\Lead::label($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-mute">
                            Private notes
                            <textarea name="staff_notes" rows="2" class="mt-1 w-full rounded-lg border border-black/10 bg-white px-2.5 py-2 text-base font-normal text-ink sm:text-sm">{{ $visitor->lead->staff_notes }}</textarea>
                        </label>
                        <button class="min-h-10 w-full rounded-lg bg-navy px-4 text-sm font-semibold text-white hover:bg-navy-2 sm:w-auto">Save follow-up</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-black/15 bg-white px-6 py-12 text-center">
            @include('partials.aiva', ['pose' => 'idle', 'size' => 64, 'class' => 'mx-auto mb-3'])
            <p class="font-semibold">No visitors found</p>
            <p class="mt-1 text-sm text-mute">@if ($filters['q'] || $filters['status'] || $filters['messages'])Try a different name, status or message filter, or <a class="font-semibold text-blue hover:underline" href="{{ route('staff.visitors') }}">clear the filters</a>.@else Check-ins from the booth will show up here. @endif</p>
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $visitors->links() }}</div>
@endsection
