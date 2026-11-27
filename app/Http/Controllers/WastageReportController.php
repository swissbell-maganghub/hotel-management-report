<?php

// [WASTAGE-REPORT] Controller for Wastage Reports
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageReport;
use App\Services\WastageExcelService;
use Barryvdh\DomPDF\Facade\Pdf; // [WASTAGE-REPORT]
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response; // [WASTAGE-REPORT]
use Symfony\Component\HttpFoundation\StreamedResponse;

class WastageReportController extends Controller
{
    /**
     * Preview file Excel sebelum commit
     */
    public function preview(Request $request, WastageExcelService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canInputDepartment(User::fnbDepartmentId()) || $user->isItAdmin(), 403, 'Akses input ditolak.');

        // [WASTAGE-REPORT] FIX: periode tidak lagi diinput, otomatis dari tanggal di file Excel
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $preview = $service->parseForPreview($request->file('excel_file'));

        if (empty($preview['period_month'])) {
            return response()->json([
                'message' => ($preview['errors'][0] ?? null)
                    ?: 'Periode tidak dapat dideteksi: tidak ada tanggal valid di kolom A (mulai baris ke-4) pada file Excel.',
            ], 422);
        }

        $previewToken = uniqid('wst_preview_', true);
        session(["wastage_preview_{$previewToken}" => $preview]);

        return response()->json([
            'preview_token' => $previewToken,
            'preview' => $preview,
        ]);
    }

    /**
     * Commit preview Excel menjadi laporan resmi
     */
    public function commit(Request $request, WastageExcelService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canInputDepartment(User::fnbDepartmentId()) || $user->isItAdmin(), 403, 'Akses input ditolak.');

        $data = $request->validate([
            'preview_token' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sessionKey = "wastage_preview_{$data['preview_token']}";
        $preview = session($sessionKey);
        if (!$preview) {
            return response()->json(['message' => 'Sesi preview kedaluwarsa. Silakan upload ulang file Excel.'], 422);
        }

        try {
            $report = $service->commitPreview($preview, $user, $data['notes'] ?? null);
            session()->forget($sessionKey);

            return response()->json([
                'message' => "Laporan Wastage {$report->report_code} berhasil disimpan.",
                'report' => $report->load(['importer', 'records']),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal menyimpan laporan: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Input data manual per baris / record harian
     */
    public function storeDaily(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canInputDepartment(User::fnbDepartmentId()) || $user->isItAdmin(), 403, 'Akses input ditolak.');

        $validated = $request->validate([
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'record_date' => ['required', 'date_format:Y-m-d'],
            'buffet_lunch' => ['nullable', 'numeric', 'min:0'],
            'buffet_dinner' => ['nullable', 'numeric', 'min:0'],
            'buffet_gravity' => ['nullable', 'numeric', 'min:0'],
            'prep_butcher' => ['nullable', 'numeric', 'min:0'],
            'prep_main_kitchen' => ['nullable', 'numeric', 'min:0'],
            'prep_pastry' => ['nullable', 'numeric', 'min:0'],
            'prep_cold_kitchen' => ['nullable', 'numeric', 'min:0'],
            'plate_waste' => ['nullable', 'numeric', 'min:0'],
            'breakfast_cafe' => ['nullable', 'numeric', 'min:0'],
            'covers' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // [WASTAGE-REPORT] Sinkronkan periode Masehi dengan tanggal record_date & hindari tabrakan report_code
        $periodMonth = substr($validated['record_date'], 0, 7);

        $report = WastageReport::where('period_month', $periodMonth)
            ->where('source_type', 'manual')
            ->first();

        if (! $report) {
            $ym = str_replace('-', '', $periodMonth);
            $latestCode = WastageReport::where('report_code', 'like', "WST-{$ym}-%")
                ->orderByDesc('id')
                ->value('report_code');
            $seq = 1;
            if ($latestCode && preg_match('/(\d+)$/', $latestCode, $m)) {
                $seq = (int) $m[1] + 1;
            }
            $code = sprintf('WST-%s-%03d', $ym, $seq);

            $report = WastageReport::create([
                'report_code' => $code,
                'period_month' => $periodMonth,
                'imported_by' => $user->id,
                'imported_at' => now(),
                'original_filename' => 'Manual Input',
                'source_type' => 'manual',
            ]);
        }

        // Hitung total KG
        $bLunch = (float)($validated['buffet_lunch'] ?? 0);
        $bDinner = (float)($validated['buffet_dinner'] ?? 0);
        $bGravity = (float)($validated['buffet_gravity'] ?? 0);
        $pButcher = (float)($validated['prep_butcher'] ?? 0);
        $pMain = (float)($validated['prep_main_kitchen'] ?? 0);
        $pPastry = (float)($validated['prep_pastry'] ?? 0);
        $pCold = (float)($validated['prep_cold_kitchen'] ?? 0);
        $plate = (float)($validated['plate_waste'] ?? 0);
        $breakfast = (float)($validated['breakfast_cafe'] ?? 0);
        $covers = (int)$validated['covers'];

        $totalKg = round($bLunch + $bDinner + $bGravity + $pButcher + $pMain + $pPastry + $pCold + $plate + $breakfast, 2);
        $kgPerCover = $covers > 0 ? round($totalKg / $covers, 4) : 0;

        // Cari record YTD sebelumnya pada report ini
        $lastRec = WastageRecord::where('wastage_report_id', $report->id)
            ->where('record_date', '<', $validated['record_date'])
            ->orderByDesc('record_date')
            ->first();

        $prevYtdKg = $lastRec ? (float)$lastRec->ytd_kg : 0.0;
        $prevYtdCover = $lastRec ? (int)$lastRec->ytd_cover : 0;

        $ytdKg = $prevYtdKg + $totalKg;
        $ytdCover = $prevYtdCover + $covers;

        WastageRecord::updateOrCreate(
            [
                'wastage_report_id' => $report->id,
                'record_date' => $validated['record_date'],
            ],
            [
                'buffet_lunch' => $bLunch,
                'buffet_dinner' => $bDinner,
                'buffet_gravity' => $bGravity,
                'prep_butcher' => $pButcher,
                'prep_main_kitchen' => $pMain,
                'prep_pastry' => $pPastry,
                'prep_cold_kitchen' => $pCold,
                'plate_waste' => $plate,
                'breakfast_cafe' => $breakfast,
                'total_kg' => $totalKg,
                'covers' => $covers,
                'ytd_kg' => $ytdKg,
                'ytd_cover' => $ytdCover,
                'kg_per_cover' => $kgPerCover,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $report->recalculateTotals();

        AuditLog::record(
            $user,
            'wastage_record.save',
            'Wastage Report',
            "Catatan data harian {$validated['record_date']} disimpan ke laporan {$report->report_code} ({$totalKg} KG, {$covers} Cover)."
        );

        return redirect()->route('workspace.index', ['tab' => 'wastage', 'report_id' => $report->id])
            ->with('status', 'Data harian berhasil disimpan ke Wastage Report.');
    }

    /**
     * Download template Excel
     */
    public function template(WastageExcelService $service): StreamedResponse
    {
        return $service->downloadTemplate();
    }

    /**
     * Export Wastage Report ke Excel
     */
    public function export(WastageReport $report, WastageExcelService $service): StreamedResponse
    {
        return $service->exportReport($report);
    }

    /**
     * [WASTAGE-REPORT] Ekspor PDF Bulanan seluruh baris harian pada bulan Masehi terpilih
     */
    public function exportMonthlyPdf(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessWastage(), 403, 'Akses ekspor ditolak.');

        $period = trim((string) $request->query('period', date('Y-m')));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            abort(422, 'Format periode bulan tidak valid (gunakan YYYY-MM).');
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$period}-01")->startOfMonth();
        $startDate = $start->format('Y-m-d');
        $endDate = $start->copy()->endOfMonth()->format('Y-m-d');

        $records = WastageRecord::with('report.importer')
            ->whereBetween('record_date', [$startDate, $endDate])
            ->orderBy('record_date')
            ->orderBy('id')
            ->get();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthNumber = (int) $start->format('n');
        $periodLabel = ($monthNames[$monthNumber] ?? $start->format('F')) . ' ' . $start->format('Y');

        $totalKg = round((float) $records->sum('total_kg'), 2);
        $totalCover = (int) $records->sum('covers');
        $kgPerCover = $totalCover > 0 ? round($totalKg / $totalCover, 4) : 0.0;

        $isPreview = $request->query('mode') === 'preview';
        if (! $isPreview) {
            AuditLog::record(
                $user,
                'wastage_report.export_pdf',
                'Wastage Report',
                "Ekspor PDF bulanan Food Waste periode {$period} ({$records->count()} hari)."
            );
        }

        $pdf = Pdf::loadView('exports.wastage-monthly-pdf', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'records' => $records,
            'totalKg' => $totalKg,
            'totalCover' => $totalCover,
            'kgPerCover' => $kgPerCover,
            'generatedBy' => $user,
        ])->setPaper('a4', 'landscape');

        $filename = "food_waste_report_{$period}.pdf";

        return $isPreview ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /**
     * Hapus Wastage Report
     */
    public function destroy(WastageReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = request()->user();
        abort_unless($user->isItAdmin() || $user->canInputDepartment(User::fnbDepartmentId()), 403);

        $code = $report->report_code;
        $report->delete();

        AuditLog::record($user, 'wastage_report.delete', 'Wastage Report', "Laporan Wastage {$code} dihapus.");

        return redirect()->route('workspace.index', ['tab' => 'wastage'])
            ->with('status', "Laporan {$code} berhasil dihapus.");
    }
}
