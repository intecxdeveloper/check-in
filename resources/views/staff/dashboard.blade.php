@extends('layouts.staff')

@section('title', 'Overview')

@php
    $interestMax = max(1, (int) ($byInterest->max('total') ?? 0));
    $regionMax = max(1, (int) ($byRegion->max('total') ?? 0));
@endphp

@section('content')
<h1 class="sr-only">Overview</h1>
<dl class="grid grid-cols-3 divide-x divide-black/[0.06] overflow-hidden rounded-2xl border border-black/[0.06] bg-white">
    @foreach ([['Check-ins', $checkIns], ['Companies', $companies], ['On the map', $publicCompanies]] as [$label, $value])
        <div class="px-4 py-4 sm:px-6 sm:py-5">
            <dt class="text-xs font-semibold text-mute sm:text-sm">{{ $label }}</dt>
            <dd class="staff-num mt-1 text-2xl font-bold sm:text-4xl">{{ number_format($value) }}</dd>
        </div>
    @endforeach
</dl>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-black/[0.06] bg-white p-5 lg:col-span-2" aria-labelledby="recent-heading">
        <div class="flex items-center justify-between gap-3">
            <h2 id="recent-heading" class="text-base font-bold">Latest check-ins</h2>
            <a href="{{ route('staff.visitors') }}" class="text-sm font-semibold text-blue hover:underline">All visitors</a>
        </div>
        <ul class="mt-3 divide-y divide-black/[0.06]">
            @forelse ($recent as $visitor)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $visitor->full_name }}</p>
                        <p class="truncate text-xs text-mute">{{ $visitor->company?->name ?: 'No company' }} · <time datetime="{{ $visitor->created_at?->toIso8601String() }}">{{ $visitor->created_at?->diffForHumans() }}</time></p>
                    </div>
                    @if ($visitor->lead)
                        <span class="staff-pill" data-status="{{ $visitor->lead->status }}">{{ str_replace('-', ' ', $visitor->lead->status) }}</span>
                    @endif
                </li>
            @empty
                <li class="py-10 text-center text-sm text-mute">@include('partials.aiva', ['pose' => 'idle', 'size' => 56, 'class' => 'mx-auto mb-3'])No check-ins yet. They appear here the moment a visitor submits the form.</li>
            @endforelse
        </ul>
    </section>

    <div class="space-y-6">
        <section class="rounded-2xl border border-black/[0.06] bg-white p-5">
            <h2 class="text-base font-bold">Lead pipeline</h2>
            <ul class="mt-3 space-y-2.5 text-sm">
                @foreach (['new','follow-up','contacted','qualified','closed'] as $status)
                    @php $count = (int) ($byStatus[$status] ?? 0); @endphp
                    <li class="flex items-center justify-between gap-3">
                        <span class="staff-pill" data-status="{{ $status }}">{{ str_replace('-', ' ', $status) }}</span>
                        <span class="staff-num font-semibold">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
        @foreach ([['Top interests', $byInterest, 'name', $interestMax], ['Regions', $byRegion, 'label', $regionMax]] as [$title, $rows, $key, $max])
            <section class="rounded-2xl border border-black/[0.06] bg-white p-5">
                <h2 class="text-base font-bold">{{ $title }}</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($rows as $row)
                        <li>
                            <div class="flex justify-between gap-3"><span>{{ $row->{$key} }}</span><span class="staff-num font-semibold">{{ $row->total }}</span></div>
                            <div class="staff-bar mt-1.5" aria-hidden="true"><span style="width: {{ round($row->total / $max * 100) }}%"></span></div>
                        </li>
                    @empty
                        <li class="text-mute">Nothing yet.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>
@endsection
