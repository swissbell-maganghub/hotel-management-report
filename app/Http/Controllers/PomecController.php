<?php

namespace App\Http\Controllers;

use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\User;
use App\Services\PomecExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PomecController extends Controller
{
    public function preview(Request $request, PomecExcelService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('extraPermissions');
        abort_unless($user->canModifyPomec(), 403);

        $validated = $request->validate([
            'utility_type' => ['required', Rule::in(array_keys(PomecRecord::TYPES))],
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'replace_report_id' => ['nullable', 'integer'],
        ]);

        $replace = $this->replaceTarget($validated['replace_report_id'] ?? null, $user);
        if ($replace && $replace->pomecImport->utility_type !== $validated['utility_type']) {
            return response()->json(['message' => 'Jenis utilitas harus sama dengan laporan yang diunggah ulang.'], 422);
        }

        $preview = $service->parseForPreview(
            $request->file('excel_file'),
            $validated['utility_type'],
            $validated['period_month'],
            $replace?->pomec_import_id
        );
        $preview['replace_report_id'] = $replace?->id;

        $previewToken = uniqid('pomec_', true);
        session(["pomec_preview_{$previewToken}" => $preview]);

        return response()->json([
            'preview_token' => $previewToken,
            'preview' => $preview,
        ]);
    }

    public function commit(Request $request, PomecExcelService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('extraPermissions');
        abort_unless($user->canModifyPomec(), 403);

        $data = $request->validate([
            'preview_token' => ['required', 'string'],
            'intent' => ['nullable', Rule::in(['draft', 'submit'])],
        ]);

        $sessionKey = "pomec_preview_{$data['preview_token']}";
        $preview = session($sessionKey);
        if (! $preview) {
            return response()->json(['message' => 'Sesi preview kedaluwarsa. Silakan unggah ulang file Excel.'], 422);
        }

        try {
            // Status laporan dicek ulang: bisa saja sudah berubah sejak pratinjau.
            $replace = $this->replaceTarget($preview['replace_report_id'] ?? null, $user);
            $oldImportId = $replace?->pomec_import_id;
            $intent = $data['intent'] ?? 'submit';
            $import = $service->commitPreview($preview, $user, $intent, $replace);
            session()->forget($sessionKey);
            $report = $import->report()->with(['department', 'template', 'creator', 'reviewer', 'histories.user'])->first();

            // Kode batch tetap tercatat di riwayat & audit log; popup cukup bahasa sehari-hari.
            return response()->json([
                'message' => "{$import->total_rows} data {$this->describe($import)} "
                    .($intent === 'draft' && ! $replace ? 'disimpan sebagai draft.' : "dikirim ke HOD ({$report->report_code})."),
                'import' => $import->load(['uploader:id,name', 'records', 'report:id,pomec_import_id,report_code,status']),
                'report' => $report,
                'replaced_import_id' => $oldImportId,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, PomecImport $import, PomecExcelService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('extraPermissions');
        abort_unless($user->canDeletePomec(), 403);

        $message = "{$import->total_rows} data {$this->describe($import)} dihapus.";
        $service->deleteImport($import, $user);

        return response()->json(['message' => $message]);
    }

    /** Laporan POMEC yang dikembalikan HOD dan boleh diunggah ulang oleh pengguna ini. */
    private function replaceTarget(?int $reportId, User $user): ?DepartmentReport
    {
        if (! $reportId) {
            return null;
        }
        $report = DepartmentReport::with('pomecImport')->findOrFail($reportId);
        abort_unless($report->pomecImport && $user->canInputDepartment($report->department_id), 403);
        if ($report->status !== DepartmentReport::STATUS_RETURNED) {
            abort(response()->json(['message' => 'Hanya laporan yang dikembalikan HOD yang bisa diunggah ulang.'], 422));
        }

        return $report;
    }

    /** Contoh: "Listrik 26 November 2026" atau "Listrik 1–25 Oktober 2026" (unggahan harian). */
    private function describe(PomecImport $import): string
    {
        $first = $import->records()->min('reading_date');
        $last = $import->records()->max('reading_date');
        $month = Carbon::parse($last ?? "{$import->period_month}-01")->locale('id')->translatedFormat('F Y');
        $days = $first ? Carbon::parse($first)->day.($first === $last ? '' : '–'.Carbon::parse($last)->day).' ' : '';

        return PomecRecord::TYPES[$import->utility_type]['id'].' '.$days.$month;
    }

    public function exportExcel(Request $request, string $utilityType, PomecExcelService $service): StreamedResponse
    {
        abort_unless($request->user()->canAccessPomec(), 403);
        abort_unless(isset(PomecRecord::TYPES[$utilityType]), 404);
        $request->validate(['period_month' => ['nullable', 'date_format:Y-m']]);

        return $service->exportExcel($utilityType, $request->query('period_month'));
    }

    public function exportPdf(Request $request, string $utilityType): Response
    {
        abort_unless($request->user()->canAccessPomec(), 403);
        abort_unless(isset(PomecRecord::TYPES[$utilityType]), 404);
        $request->validate(['period_month' => ['nullable', 'date_format:Y-m']]);

        $periodMonth = $request->query('period_month');
        $records = PomecRecord::with('import')
            ->where('utility_type', $utilityType)
            ->when($periodMonth, fn ($query) => $query->whereHas('import', fn ($q) => $q->where('period_month', $periodMonth)))
            ->orderBy('reading_date')
            ->get();

        $pdf = Pdf::loadView('exports.pomec-pdf', [
            'utilityType' => $utilityType,
            'records' => $records,
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('pomec_'.$utilityType.'_'.($periodMonth ?: 'all').'.pdf');
    }
}
