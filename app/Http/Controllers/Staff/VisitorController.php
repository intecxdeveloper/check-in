<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $visitors = Visitor::query()
            ->with(['company.services', 'lead.assignee'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('status', $status)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('staff.visitors', [
            'visitors' => $visitors,
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => Lead::STATUSES,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function updateLead(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Lead::STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead->update($data);

        return back()->with('status', 'Lead updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'expo-visitors-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Visitor Name',
                'Job Title',
                'Email',
                'Phone',
                'Company',
                'City',
                'Region',
                'Country',
                'Interests',
                'Public Display',
                'Follow-up Consent',
                'Lead Status',
                'Assigned To',
                'Staff Notes',
                'Checked In At',
            ]);

            Visitor::query()
                ->with(['company.services', 'lead.assignee'])
                ->when($request->filled('status'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('status', $request->string('status'))))
                ->when($request->filled('q'), function ($query) use ($request) {
                    $search = $request->string('q')->toString();
                    $query->where(function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                    });
                })
                ->latest()
                ->chunk(200, function ($visitors) use ($handle) {
                    foreach ($visitors as $visitor) {
                        fputcsv($handle, [
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
                            $visitor->follow_up_consent_at ? 'yes' : 'no',
                            $visitor->lead?->status,
                            $visitor->lead?->assignee?->name,
                            $visitor->lead?->staff_notes,
                            optional($visitor->created_at)?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
