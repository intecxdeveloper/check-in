<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Lead::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byInterest = DB::table('company_service')
            ->join('services', 'services.id', '=', 'company_service.service_id')
            ->select('services.name', DB::raw('count(*) as total'))
            ->groupBy('services.name')
            ->orderByDesc('total')
            ->get();

        $byRegion = Company::query()
            ->selectRaw("COALESCE(NULLIF(region, ''), NULLIF(country, ''), 'Unknown') as label, count(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('staff.dashboard', [
            'checkIns' => Visitor::count(),
            'companies' => Company::count(),
            'publicCompanies' => Company::where('public_display_enabled', true)->count(),
            'byStatus' => $byStatus,
            'byInterest' => $byInterest,
            'byRegion' => $byRegion,
            'recent' => Visitor::with(['company', 'lead'])->latest()->limit(6)->get(),
        ]);
    }
}
