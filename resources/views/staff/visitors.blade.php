@extends('layouts.staff')

@section('title', 'Visitors')

@section('content')
<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-xl font-semibold">Visitors & leads</h2>
        <p class="text-sm text-slate-600">Search, update status, and export permitted records.</p>
    </div>
    <a href="{{ route('staff.export', request()->query()) }}"
       class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white">
        Export CSV
    </a>
</div>

<form method="GET" class="mb-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name, email, company"
           class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm">
    <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        <option value="">All statuses</option>
        @foreach ($statuses as $status)
            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <button class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-medium text-white">Filter</button>
</form>

<div class="space-y-4">
    @forelse ($visitors as $visitor)
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">{{ $visitor->full_name }}</h3>
                    <p class="text-sm text-slate-600">
                        {{ $visitor->job_title ?: '—' }} · {{ $visitor->company?->name }}
                    </p>
                    <p class="mt-2 text-sm">
                        <span class="text-slate-500">Email:</span> {{ $visitor->email ?: '—' }}
                        · <span class="text-slate-500">Phone:</span> {{ $visitor->phone ?: '—' }}
                    </p>
                    <p class="mt-1 text-sm">
                        <span class="text-slate-500">Location:</span>
                        {{ $visitor->company?->locationLabel() ?: '—' }}
                        · Public map: {{ $visitor->company?->public_display_enabled ? 'yes' : 'no' }}
                        · Follow-up: {{ $visitor->follow_up_consent_at ? 'yes' : 'no' }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($visitor->company?->services ?? [] as $service)
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium text-slate-800"
                                  style="background: {{ $service->color }}33">{{ $service->name }}</span>
                        @endforeach
                    </div>
                </div>

                @if ($visitor->lead)
                    <form method="POST" action="{{ route('staff.leads.update', $visitor->lead) }}" class="w-full max-w-md space-y-3">
                        @csrf
                        @method('PATCH')
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium">Lead status</span>
                            <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($visitor->lead->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium">Assigned to</span>
                            <select name="assigned_to" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                                <option value="">Unassigned</option>
                                @foreach ($staff as $user)
                                    <option value="{{ $user->id }}" @selected($visitor->lead->assigned_to === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium">Staff notes (private)</span>
                            <textarea name="staff_notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2">{{ $visitor->lead->staff_notes }}</textarea>
                        </label>
                        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            No visitors match your filters.
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $visitors->links() }}</div>
@endsection
