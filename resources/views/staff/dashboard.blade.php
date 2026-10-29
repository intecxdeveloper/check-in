@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
<div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Check-ins</p>
        <p class="mt-1 text-3xl font-semibold">{{ $checkIns }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Companies</p>
        <p class="mt-1 text-3xl font-semibold">{{ $companies }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Public on map</p>
        <p class="mt-1 text-3xl font-semibold">{{ $publicCompanies }}</p>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Recent visitors</h2>
            <a href="{{ route('staff.visitors') }}" class="text-sm text-sky-700 hover:underline">View all</a>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Company</th>
                        <th class="py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $visitor)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3">{{ $visitor->full_name }}</td>
                            <td class="py-3 pr-3">{{ $visitor->company?->name }}</td>
                            <td class="py-3 pr-3 capitalize">{{ $visitor->lead?->status }}</td>
                            <td class="py-3">{{ $visitor->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-slate-500">No visitors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Lead status</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach (['new','follow-up','contacted','qualified','closed'] as $status)
                    <li class="flex justify-between">
                        <span class="capitalize">{{ $status }}</span>
                        <strong>{{ $byStatus[$status] ?? 0 }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Top interests</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($byInterest as $row)
                    <li class="flex justify-between">
                        <span>{{ $row->name }}</span>
                        <strong>{{ $row->total }}</strong>
                    </li>
                @empty
                    <li class="text-slate-500">No data yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Regions</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($byRegion as $row)
                    <li class="flex justify-between">
                        <span>{{ $row->label }}</span>
                        <strong>{{ $row->total }}</strong>
                    </li>
                @empty
                    <li class="text-slate-500">No data yet.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>
@endsection
