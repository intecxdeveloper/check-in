<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Visitor;
use App\Support\MapCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $visitors = $this->filteredVisitors($request)
            ->with(['company.services', 'lead'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('staff.visitors', [
            'visitors' => $visitors,
            'statuses' => Lead::STATUSES,
            'filters' => $filters,
            'liveMessages' => Visitor::query()->withPublicMessage()->count(),
        ]);
    }

    public function updateLead(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Lead::STATUSES)],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead->update($data);

        return back()->with('status', 'Follow-up saved.');
    }

    public function destroyMessage(Visitor $visitor): RedirectResponse
    {
        if (! $visitor->hasPublicMessage()) {
            return back()->with('status', 'That visitor has no public message.');
        }

        $visitor->update([
            'display_message' => null,
            'display_message_removed_at' => now(),
        ]);

        MapCache::flushPublic();

        return back()->with('status', 'Public message removed from the live map.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'expo-visitors-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Claim Code',
                'Visitor Name',
                'Job Title',
                'Email',
                'Phone',
                'Company',
                'City',
                'Region',
                'Country',
                'Interests',
                'On Map',
                'Public Message',
                'Follow-up Consent',
                'Lead Status',
                'Staff Notes',
                'Checked In At',
            ]);

            $this->filteredVisitors($request)
                ->with(['company.services', 'lead'])
                ->latest()
                ->chunk(200, function ($visitors) use ($handle) {
                    foreach ($visitors as $visitor) {
                        fputcsv($handle, array_map(self::csvSafe(...), [
                            $visitor->claimCode(),
                            $visitor->full_name,
                            $visitor->job_title,
                            $visitor->email,
                            $visitor->phone,
                            $visitor->company?->name,
                            $visitor->company?->city,
                            $visitor->company?->region,
                            $visitor->company?->country,
                            $visitor->company?->services->pluck('name')->implode('; '),
                            $visitor->company?->public_display_enabled ? 'yes' : 'no',
                            $visitor->display_message,
                            $visitor->follow_up_consent_at ? 'yes' : 'no',
                            $visitor->lead?->status,
                            $visitor->lead?->staff_notes,
                            optional($visitor->created_at)?->toDateTimeString(),
                        ]));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Neutralise spreadsheet formulas (=, +, -, @, tab, CR) typed by visitors
     * so opening the export in Excel or Sheets cannot execute anything.
     */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @return array{q: string, status: string, messages: bool}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'messages' => $request->boolean('messages'),
        ];
    }

    private function filteredVisitors(Request $request): Builder
    {
        $filters = $this->filters($request);

        return Visitor::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['q'];
                $query->where(function (Builder $q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('display_message', 'like', "%{$search}%")
                        ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $q) => $q->whereHas('lead', fn (Builder $l) => $l->where('status', $filters['status'])))
            ->when($filters['messages'], fn (Builder $q) => $q->withPublicMessage());
    }
}
