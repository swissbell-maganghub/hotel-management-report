<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChatbotKnowledge;
use App\Models\ChatbotTurn;
use App\Models\Department;
use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\ReportHistory;
use App\Models\ReportTemplate;
use App\Models\TopologyComponent;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\DatabaseBackupService;
use App\Services\SystemDiagnosticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, DatabaseBackupService $backupService, SystemDiagnosticsService $diagnosticsService): View
    {
        /** @var User $user */
        $user = $request->user()->load(['department', 'extraPermissions.department']);
        $locale = session('locale', $user->locale ?: 'id');

        $departments = Department::where('is_active', true)->orderBy('id')->get();
        $accessibleDeptIds = $user->accessibleDepartmentIds();
        $inputtableDeptIds = $user->inputtableDepartmentIds();

        $templates = ReportTemplate::with('department')
            ->where('is_active', true)
            ->when(! $user->canAccessAllDepartments(), fn ($q) => $q->whereIn('department_id', $inputtableDeptIds ?: [-1]))
            ->orderBy('department_id')
            ->get();

        $reportsQuery = DepartmentReport::with(['department', 'template', 'creator', 'reviewer', 'histories.user'])
            ->latest('period_date')
            ->latest('id');

        if (! $user->canAccessAllDepartments()) {
            $reportsQuery->whereIn('department_id', $accessibleDeptIds ?: [-1]);
        }

        $reports = $reportsQuery->get();

        // Counts for KPI & tab badges
        $pendingReviewCount = $reports->whereIn('status', [
            DepartmentReport::STATUS_SUBMITTED,
            DepartmentReport::STATUS_IN_REVIEW,
        ])->count();
        $returnedCount = $reports->where('status', DepartmentReport::STATUS_RETURNED)->count();
        $reviewedCount = $reports->where('status', DepartmentReport::STATUS_REVIEWED)->count();

        // POMEC Data (scoped)
        $canPomec = $user->canAccessPomec();
        $pomecImports = $canPomec
            ? PomecImport::with('uploader')->latest('committed_at')->get()
            : collect();
        // Bulan harus utuh karena total per bulan dihitung di browser.
        // ponytail: 13 bulan terakhir (~3.000 baris); ringkas di server kalau datanya membengkak.
        $latestPomecDate = $canPomec ? PomecRecord::max('reading_date') : null;
        $pomecRecords = $latestPomecDate
            ? PomecRecord::whereDate('reading_date', '>=', Carbon::parse($latestPomecDate)->subMonthsNoOverflow(12)->startOfMonth()->toDateString())
                ->latest('reading_date')
                ->get()
            : collect();

        $electricityTotal = $canPomec
            ? (float) PomecRecord::where('utility_type', 'electricity')->sum('total_usage')
            : 0.0;
        $electricityLwbp = $canPomec
            ? (float) PomecRecord::where('utility_type', 'electricity')->sum('lwbp_value')
            : 0.0;
        $electricityWbp = $canPomec
            ? (float) PomecRecord::where('utility_type', 'electricity')->sum('wbp_value')
            : 0.0;
        $waterTotal = $canPomec
            ? (float) PomecRecord::where('utility_type', 'water')->sum('water_m3')
            : 0.0;

        // Topology (IT Admin full control & GM Read-Only)
        $topologyComponents = $user->canViewTopology()
            ? TopologyComponent::orderBy('sort_order')->get()
            : collect();

        $departmentMatrix = $user->canAccessAllDepartments()
            ? $this->buildDepartmentMatrix($departments, $reports)
            : collect();

        $hotelPulse = $user->canAccessAllDepartments()
            ? $this->buildHotelPulse($reports)
            : [];

        $topologySummary = $user->canViewTopology()
            ? $this->buildTopologySummary($topologyComponents)
            : [];

        $gmCharts = $user->canViewExecutiveDashboard()
            ? $this->buildGmCharts($reports, $pomecRecords, $hotelPulse)
            : null;

        // Admin User, Template & Audit Data (IT Admin only)
        $managedUsers = collect();
        if ($user->canManageUsers()) {
            $sessionCounts = [];
            try {
                $sessionCounts = DB::table('sessions')
                    ->selectRaw('user_id, COUNT(*) as total')
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->pluck('total', 'user_id')
                    ->all();
            } catch (\Throwable) {
                $sessionCounts = [];
            }

            $managedUsers = User::with(['department', 'extraPermissions.department'])->orderBy('id')->get()->each(function (User $u) use ($sessionCounts): void {
                $u->setAttribute('active_sessions_count', (int) ($sessionCounts[$u->id] ?? 0));
            });
        }
        $allTemplates = $user->canManageUsers()
            ? ReportTemplate::with('department')->orderBy('department_id')->get()
            : collect();
        $auditLogs = $user->canManageUsers()
            ? AuditLog::with('user')->latest('created_at')->limit(250)->get()
            : collect();
        $reportHistories = $reports->isNotEmpty()
            ? ReportHistory::with(['report.department', 'user'])
                ->whereIn('department_report_id', $reports->pluck('id'))
                ->latest('created_at')
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();
        $databaseStats = $user->canManageUsers()
            ? $backupService->inspectDatabase()
            : null;
        $backupFiles = $user->canManageUsers()
            ? $backupService->listBackups()
            : [];
        $systemDiagnostics = $user->canEditTopology()
            ? $diagnosticsService->collect($locale === 'en')
            : null;

        // Available tabs based on RBAC
        $allowedTabs = ['dashboard'];
        if ($user->isItAdmin()) {
            $allowedTabs[] = 'executive';
        }
        $allowedTabs[] = 'reports';
        if ($canPomec) {
            $allowedTabs[] = 'pomec';
        }
        if ($user->canViewTopology()) {
            $allowedTabs[] = 'topology';
        }
        if ($user->canManageUsers()) {
            $allowedTabs[] = 'admin';
            $allowedTabs[] = 'audit';
            $allowedTabs[] = 'chatbot';
        }

        $activeTab = $request->query('tab', 'dashboard');
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'dashboard';
        }

        $operationalDepartments = $departments->reject(fn ($d) => $d->code === 'ANG')->values();
        $accessibleDepts = ($user->canAccessAllDepartments()
            ? $operationalDepartments
            : $operationalDepartments->whereIn('id', $accessibleDeptIds)
        )->values();
        $requestedDept = strtoupper(trim((string) $request->query('dept', '')));
        $activeReportDept = ($activeTab === 'reports' && $accessibleDepts->contains(fn ($d) => $d->code === $requestedDept))
            ? $requestedDept
            : '';

        return view('workspace.index', [
            'user' => $user,
            'locale' => $locale,
            'activeTab' => $activeTab,
            'activeReportDept' => $activeReportDept,
            'allowedTabs' => $allowedTabs,
            'departments' => $departments,
            'accessibleDepts' => $accessibleDepts,
            'templates' => $templates,
            'allTemplates' => $allTemplates,
            'reports' => $reports,
            'pendingReviewCount' => $pendingReviewCount,
            'returnedCount' => $returnedCount,
            'reviewedCount' => $reviewedCount,
            'canPomec' => $canPomec,
            'pomecImports' => $pomecImports,
            'pomecRecords' => $pomecRecords,
            'electricityTotal' => $electricityTotal,
            'electricityLwbp' => $electricityLwbp,
            'electricityWbp' => $electricityWbp,
            'waterTotal' => $waterTotal,
            'topologyComponents' => $topologyComponents,
            'departmentMatrix' => $departmentMatrix,
            'hotelPulse' => $hotelPulse,
            'topologySummary' => $topologySummary,
            'gmCharts' => $gmCharts,
            'reportHistories' => $reportHistories,
            'databaseStats' => $databaseStats,
            'backupFiles' => $backupFiles,
            'systemDiagnostics' => $systemDiagnostics,
            'managedUsers' => $managedUsers,
            'auditLogs' => $auditLogs,
            // Chatbot knowledge base + usage insights (IT Admin): the loop that makes the assistant better over time.
            'chatbotAdmin' => $user->canManageUsers() ? [
                'knowledge' => ChatbotKnowledge::orderBy('type')->orderByDesc('priority')->orderBy('title')->get(),
                'insights' => ChatbotTurn::insights(30),
                'disabled' => ChatbotService::disabledInfo(),
            ] : null,
        ]);
    }

    /**
     * Build cross-department compliance and latest status matrix for executive visibility.
     */
    private function buildDepartmentMatrix($departments, $reports)
    {
        $byDept = $reports->groupBy('department_id');

        return $departments->map(function (Department $dept) use ($byDept) {
            $deptReports = $byDept->get($dept->id, collect());
            /** @var DepartmentReport|null $latest */
            $latest = $deptReports->first();
            $latestTs = $latest?->reviewed_at ?? $latest?->submitted_at ?? $latest?->updated_at;

            return [
                'department' => $dept,
                'latest' => $latest,
                'latest_wita' => $latestTs
                    ? $latestTs->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA'
                    : null,
                'total_count' => $deptReports->count(),
                'reviewed_count' => $deptReports->where('status', DepartmentReport::STATUS_REVIEWED)->count(),
                'pending_count' => $deptReports->where('status', DepartmentReport::STATUS_SUBMITTED)->count(),
                'returned_count' => $deptReports->where('status', DepartmentReport::STATUS_RETURNED)->count(),
            ];
        })->values();
    }

    /**
     * Extract key operational pulse indicators from latest FO, F&B, HK, and ENG reports.
     */
    private function buildHotelPulse($reports): array
    {
        $latestByTpl = function (string $code) use ($reports): ?DepartmentReport {
            return $reports->first(fn (DepartmentReport $r) => $r->template?->code === $code);
        };

        $formatTs = fn (?DepartmentReport $r): ?string => ($ts = ($r?->reviewed_at ?? $r?->submitted_at ?? $r?->updated_at))
            ? $ts->copy()->timezone('Asia/Makassar')->format('d M Y, H:i').' WITA'
            : null;

        $foReport = $latestByTpl('FO-OCCUPANCY');
        $foRows = collect($foReport?->rows_data ?? []);
        $foAvail = (int) $foRows->sum(fn ($r) => (int) ($r['available'] ?? 0));
        $foOcc = (int) $foRows->sum(fn ($r) => (int) ($r['occupied'] ?? 0));

        $fnbReport = $latestByTpl('FNB-OUTLET');
        $fnbRows = collect($fnbReport?->rows_data ?? []);
        $foodRev = (float) $fnbRows->sum(fn ($r) => (float) ($r['food_rev'] ?? 0));
        $bevRev = (float) $fnbRows->sum(fn ($r) => (float) ($r['bev_rev'] ?? 0));

        $hkReport = $latestByTpl('HK-STATUS');
        $hkRows = collect($hkReport?->rows_data ?? []);
        $hkVc = (int) $hkRows->sum(fn ($r) => (int) ($r['vc'] ?? 0));
        $hkVd = (int) $hkRows->sum(fn ($r) => (int) ($r['vd'] ?? 0));
        $hkOc = (int) $hkRows->sum(fn ($r) => (int) ($r['oc'] ?? 0));
        $hkOoo = (int) $hkRows->sum(fn ($r) => (int) ($r['ooo'] ?? 0));
        $hkTotal = $hkVc + $hkVd + $hkOc + $hkOoo;

        $engReport = $latestByTpl('ENG-MAINT');
        $engRows = collect($engReport?->rows_data ?? []);

        $finReport = $latestByTpl('FIN-RECON');
        $finRows = collect($finReport?->rows_data ?? []);

        $slsReport = $latestByTpl('SLS-PIPELINE');
        $slsRows = collect($slsReport?->rows_data ?? []);

        $hrReport = $latestByTpl('HR-ATTEND');
        $hrRows = collect($hrReport?->rows_data ?? []);
        $hrScheduled = (int) $hrRows->sum(fn ($r) => (int) ($r['scheduled'] ?? 0));
        $hrPresent = (int) $hrRows->sum(fn ($r) => (int) ($r['present'] ?? 0));

        return [
            'fo' => [
                'report_id' => $foReport?->id,
                'status' => $foReport?->status,
                'period_date' => $foReport?->period_date?->format('d M Y'),
                'updated_wita' => $formatTs($foReport),
                'available' => $foAvail,
                'occupied' => $foOcc,
                'occupancy_pct' => $foAvail > 0 ? round(($foOcc / $foAvail) * 100, 1) : 0.0,
                'arrivals' => (int) $foRows->sum(fn ($r) => (int) ($r['arrivals'] ?? 0)),
                'departures' => (int) $foRows->sum(fn ($r) => (int) ($r['departures'] ?? 0)),
            ],
            'fnb' => [
                'report_id' => $fnbReport?->id,
                'status' => $fnbReport?->status,
                'period_date' => $fnbReport?->period_date?->format('d M Y'),
                'updated_wita' => $formatTs($fnbReport),
                'covers' => (int) $fnbRows->sum(fn ($r) => (int) ($r['covers'] ?? 0)),
                'food_rev' => $foodRev,
                'bev_rev' => $bevRev,
                'total_rev' => $foodRev + $bevRev,
            ],
            'hk' => [
                'report_id' => $hkReport?->id,
                'status' => $hkReport?->status,
                'period_date' => $hkReport?->period_date?->format('d M Y'),
                'updated_wita' => $formatTs($hkReport),
                'vc' => $hkVc,
                'vd' => $hkVd,
                'oc' => $hkOc,
                'ooo' => $hkOoo,
                'total_rooms' => $hkTotal,
                'ready_pct' => $hkTotal > 0 ? round((($hkVc + $hkOc) / $hkTotal) * 100, 1) : 0.0,
            ],
            'eng' => [
                'report_id' => $engReport?->id,
                'status' => $engReport?->status,
                'period_date' => $engReport?->period_date?->format('d M Y'),
                'updated_wita' => $formatTs($engReport),
                'total_items' => $engRows->count(),
                'issues_count' => $engRows->filter(fn ($r) => ($r['condition'] ?? 'Normal') !== 'Normal')->count(),
                'downtime_min' => (int) $engRows->sum(fn ($r) => (int) ($r['downtime_min'] ?? 0)),
            ],
            'fin' => [
                'report_id' => $finReport?->id,
                'status' => $finReport?->status,
                'gross_amount' => (float) $finRows->sum(fn ($r) => (float) ($r['gross_amount'] ?? 0)),
                'variance' => (float) $finRows->sum(fn ($r) => (float) ($r['variance'] ?? 0)),
                'updated_wita' => $formatTs($finReport),
            ],
            'sls' => [
                'report_id' => $slsReport?->id,
                'status' => $slsReport?->status,
                'est_revenue' => (float) $slsRows->sum(fn ($r) => (float) ($r['est_revenue'] ?? 0)),
                'room_nights' => (int) $slsRows->sum(fn ($r) => (int) ($r['room_nights'] ?? 0)),
                'updated_wita' => $formatTs($slsReport),
            ],
            'hr' => [
                'report_id' => $hrReport?->id,
                'status' => $hrReport?->status,
                'scheduled' => $hrScheduled,
                'present' => $hrPresent,
                'attendance_pct' => $hrScheduled > 0 ? round(($hrPresent / $hrScheduled) * 100, 1) : 0.0,
                'updated_wita' => $formatTs($hrReport),
            ],
        ];
    }

    /**
     * Summarize infrastructure topology health and latest WITA check timestamp.
     */
    private function buildTopologySummary($topologyComponents): array
    {
        $latestChecked = $topologyComponents->max('last_checked_at');

        return [
            'total' => $topologyComponents->count(),
            'healthy' => $topologyComponents->where('status', 'healthy')->count(),
            'warning' => $topologyComponents->where('status', 'warning')->count(),
            'degraded' => $topologyComponents->where('status', 'degraded')->count(),
            'last_checked_wita' => $latestChecked
                ? $latestChecked->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA'
                : null,
        ];
    }

    /**
     * Prepare clean time-series and distribution datasets for GM Executive Dashboard charts.
     */
    private function buildGmCharts($reports, $pomecRecords, array $hotelPulse): array
    {
        // Grafik ini hanya Listrik dan Air; tanggal gas/solar tidak boleh ikut mengisi slot (jadi batang nol).
        $byDate = $pomecRecords
            ->whereIn('utility_type', ['electricity', 'water'])
            ->groupBy(fn (PomecRecord $rec) => $rec->reading_date->format('Y-m-d'))
            ->sortKeys()
            ->slice(-10);

        $pomecLabels = [];
        $pomecLwbp = [];
        $pomecWbp = [];
        $pomecWater = [];

        foreach ($byDate as $dateKey => $dayRecords) {
            $pomecLabels[] = Carbon::parse($dateKey)->format('d M');
            $elecRows = $dayRecords->where('utility_type', 'electricity');
            $waterRows = $dayRecords->where('utility_type', 'water');
            $pomecLwbp[] = round((float) $elecRows->sum('lwbp_value'), 1);
            $pomecWbp[] = round((float) $elecRows->sum('wbp_value'), 1);
            $pomecWater[] = round((float) $waterRows->sum('water_m3'), 1);
        }

        $hk = $hotelPulse['hk'] ?? [];

        return [
            'pomec' => [
                'labels' => $pomecLabels,
                'lwbp' => $pomecLwbp,
                'wbp' => $pomecWbp,
                'water' => $pomecWater,
            ],
            'reportStatus' => [
                'reviewed' => $reports->where('status', DepartmentReport::STATUS_REVIEWED)->count(),
                'submitted' => $reports->whereIn('status', [DepartmentReport::STATUS_SUBMITTED, DepartmentReport::STATUS_IN_REVIEW])->count(),
                'returned' => $reports->where('status', DepartmentReport::STATUS_RETURNED)->count(),
                'draft' => $reports->where('status', DepartmentReport::STATUS_DRAFT)->count(),
                'total' => $reports->count(),
            ],
            'hkRooms' => [
                'vc' => (int) ($hk['vc'] ?? 0),
                'oc' => (int) ($hk['oc'] ?? 0),
                'vd' => (int) ($hk['vd'] ?? 0),
                'ooo' => (int) ($hk['ooo'] ?? 0),
            ],
        ];
    }
}
