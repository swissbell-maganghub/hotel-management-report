<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewDepartmentReportRequest;
use App\Http\Requests\StoreDepartmentReportRequest;
use App\Http\Requests\UpdateDepartmentReportRequest;
use App\Models\AuditLog;
use App\Models\DepartmentReport;
use App\Models\ReportHistory;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\DepartmentReportExportService;
use App\Services\DepartmentReportService;
use App\Services\PomecExcelService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentReportController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreDepartmentReportRequest $request, DepartmentReportService $reportService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $template = ReportTemplate::with('department')
            ->where('is_active', true)
            ->findOrFail($validated['report_template_id']);

        if (! $user->canInputDepartment($template->department_id)) {
            return response()->json([
                'message' => session('locale', 'id') === 'en'
                    ? 'You do not have input permission for this department.'
                    : 'Anda tidak memiliki izin input pada departemen ini.',
            ], 403);
        }

        $rows = $reportService->validateAndSanitizeRows($template, json_decode($validated['rows_data'], true));

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $attachmentPath = $file->store('report-attachments', 'local');
        }

        $report = DB::transaction(function () use ($validated, $template, $rows, $attachmentPath, $attachmentName, $user): DepartmentReport {
            $deptCode = $template->department->code;
            $code = DepartmentReport::nextCode($deptCode, $template->department_id, $validated['period_date']);

            $status = $validated['intent'] === 'submit'
                ? DepartmentReport::STATUS_SUBMITTED
                : DepartmentReport::STATUS_DRAFT;

            $report = DepartmentReport::create([
                'report_code' => $code,
                'department_id' => $template->department_id,
                'report_template_id' => $template->id,
                'title' => $validated['title'],
                'period_type' => $validated['period_type'],
                'period_date' => $validated['period_date'],
                'status' => $status,
                'rows_data' => $rows,
                'summary_notes' => $validated['summary_notes'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'created_by' => $user->id,
                'submitted_at' => $status === DepartmentReport::STATUS_SUBMITTED ? now() : null,
            ]);

            ReportHistory::create([
                'department_report_id' => $report->id,
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => $status,
                'action' => $status === DepartmentReport::STATUS_SUBMITTED ? 'submitted' : 'created',
                'note' => $status === DepartmentReport::STATUS_SUBMITTED
                    ? 'Laporan dibuat dan langsung dikirim ke HOD.'
                    : 'Draft laporan disimpan.',
                'created_at' => now(),
            ]);

            AuditLog::record($user, 'report.'.$status, 'Reports', "Laporan {$report->report_code} ({$deptCode}) disimpan dengan status {$status}.");

            return $report->load(['department', 'template', 'creator', 'reviewer', 'histories.user']);
        });

        $isEn = session('locale', 'id') === 'en';

        return response()->json([
            'message' => $report->status === 'submitted'
                ? ($isEn ? 'Report submitted for HOD review.' : 'Laporan berhasil dikirim untuk pemeriksaan HOD.')
                : ($isEn ? 'Report draft saved.' : 'Draft laporan berhasil disimpan.'),
            'report' => $report,
        ], 201);
    }

    public function update(UpdateDepartmentReportRequest $request, DepartmentReport $report, DepartmentReportService $reportService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if ($report->pomec_import_id) {
            return response()->json(['message' => session('locale', 'id') === 'en'
                ? 'This report comes from the POMEC Excel file. Use Re-upload instead.'
                : 'Isi laporan ini berasal dari file Excel POMEC. Gunakan Unggah Ulang.'], 422);
        }

        $report->loadMissing('template');
        $rows = $reportService->validateAndSanitizeRows($report->template, json_decode($validated['rows_data'], true));

        if ($request->hasFile('attachment')) {
            if ($report->attachment_path && Storage::disk('local')->exists($report->attachment_path)) {
                Storage::disk('local')->delete($report->attachment_path);
            }
            $file = $request->file('attachment');
            $report->attachment_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $report->attachment_path = $file->store('report-attachments', 'local');
        }

        $fromStatus = $report->status;
        $toStatus = $validated['intent'] === 'submit'
            ? DepartmentReport::STATUS_SUBMITTED
            : DepartmentReport::STATUS_DRAFT;

        DB::transaction(function () use ($report, $validated, $rows, $fromStatus, $toStatus, $user): void {
            $report->fill([
                'title' => $validated['title'],
                'period_type' => $validated['period_type'],
                'period_date' => $validated['period_date'],
                'rows_data' => $rows,
                'summary_notes' => $validated['summary_notes'] ?? null,
                'status' => $toStatus,
                'submitted_at' => $toStatus === DepartmentReport::STATUS_SUBMITTED ? now() : $report->submitted_at,
            ])->save();

            ReportHistory::create([
                'department_report_id' => $report->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'action' => $toStatus === DepartmentReport::STATUS_SUBMITTED ? 'submitted' : 'updated',
                'note' => $toStatus === DepartmentReport::STATUS_SUBMITTED
                    ? 'Laporan diperbarui dan dikirim ulang untuk review HOD.'
                    : 'Draft laporan diperbarui.',
                'created_at' => now(),
            ]);

            AuditLog::record($user, 'report.updated', 'Reports', "Laporan {$report->report_code} diperbarui ({$fromStatus} -> {$toStatus}).");
        });

        $isEn = session('locale', 'id') === 'en';

        return response()->json([
            'message' => $toStatus === 'submitted'
                ? ($isEn ? 'Report submitted to HOD.' : 'Laporan berhasil dikirim ke HOD.')
                : ($isEn ? 'Draft changes saved.' : 'Perubahan draft disimpan.'),
            'report' => $report->fresh(['department', 'template', 'creator', 'reviewer', 'histories.user']),
        ]);
    }

    /** Draft → Submitted tanpa mengubah isi (dipakai laporan POMEC). */
    public function submit(Request $request, DepartmentReport $report): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($report->status === DepartmentReport::STATUS_DRAFT && $report->isEditableBy($user), 403);

        DB::transaction(function () use ($report, $user): void {
            $report->update(['status' => DepartmentReport::STATUS_SUBMITTED, 'submitted_at' => now()]);
            ReportHistory::create([
                'department_report_id' => $report->id,
                'user_id' => $user->id,
                'from_status' => DepartmentReport::STATUS_DRAFT,
                'to_status' => DepartmentReport::STATUS_SUBMITTED,
                'action' => 'submitted',
                'note' => 'Laporan dikirim untuk review HOD.',
                'created_at' => now(),
            ]);
            AuditLog::record($user, 'report.submitted', 'Reports', "Laporan {$report->report_code} dikirim ke HOD.");
        });

        return response()->json([
            'message' => session('locale', 'id') === 'en' ? 'Report submitted to HOD.' : 'Laporan berhasil dikirim ke HOD.',
            'report' => $report->fresh(['department', 'template', 'creator', 'reviewer', 'histories.user']),
        ]);
    }

    public function review(ReviewDepartmentReportRequest $request, DepartmentReport $report): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $fromStatus = $report->status;
        $toStatus = $validated['decision'];

        DB::transaction(function () use ($report, $fromStatus, $toStatus, $validated, $user): void {
            $defaultNote = match ($toStatus) {
                DepartmentReport::STATUS_IN_REVIEW => 'HOD memeriksa kelengkapan dan kesesuaian laporan.',
                DepartmentReport::STATUS_RETURNED => 'Laporan dikembalikan untuk perbaikan.',
                default => 'Pemeriksaan HOD selesai — tersedia untuk monitoring manajemen.',
            };

            $report->update([
                'status' => $toStatus,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? ($toStatus === DepartmentReport::STATUS_IN_REVIEW ? $report->review_note : null),
            ]);

            ReportHistory::create([
                'department_report_id' => $report->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'action' => $toStatus,
                'note' => ($validated['review_note'] ?? null) ?: $defaultNote,
                'created_at' => now(),
            ]);

            AuditLog::record($user, 'report.'.$toStatus, 'Reports', "HOD menandai laporan {$report->report_code} sebagai {$toStatus}.");
        });

        $isEn = session('locale', 'id') === 'en';
        $message = match ($toStatus) {
            DepartmentReport::STATUS_IN_REVIEW => $isEn
                ? 'Report marked as In Review by HOD.'
                : 'Laporan ditandai sedang diperiksa (Review HOD).',
            DepartmentReport::STATUS_REVIEWED => $isEn
                ? 'Report marked as Reviewed and available for management monitoring.'
                : 'Laporan ditandai Reviewed dan tersedia untuk monitoring manajemen.',
            default => $isEn
                ? 'Report returned for revision.'
                : 'Laporan dikembalikan ke staf untuk perbaikan.',
        };

        return response()->json([
            'message' => $message,
            'report' => $report->fresh(['department', 'template', 'creator', 'reviewer', 'histories.user']),
        ]);
    }

    public function destroy(Request $request, DepartmentReport $report, PomecExcelService $pomec): JsonResponse
    {
        $this->authorize('delete', $report);

        $code = $report->report_code;
        // Draft POMEC: unggahannya ikut dihapus (file diarsipkan, laporan terhapus bersama batch).
        if ($report->pomecImport) {
            $pomec->deleteImport($report->pomecImport, $request->user());

            return response()->json([
                'message' => session('locale', 'id') === 'en' ? "Draft {$code} deleted." : "Draft {$code} berhasil dihapus.",
                'pomec_import_id' => $report->pomec_import_id,
            ]);
        }

        if ($report->attachment_path && Storage::disk('local')->exists($report->attachment_path)) {
            Storage::disk('local')->delete($report->attachment_path);
        }
        $report->delete();

        AuditLog::record($request->user(), 'report.deleted', 'Reports', "Draft laporan {$code} dihapus.");

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Draft {$code} deleted."
                : "Draft {$code} berhasil dihapus.",
        ]);
    }

    public function exportExcel(Request $request, DepartmentReport $report, DepartmentReportExportService $exporter): StreamedResponse
    {
        $this->authorize('view', $report);
        AuditLog::record($request->user(), 'report.export_excel', 'Reports', "Ekspor Excel laporan {$report->report_code}.");

        return $exporter->exportExcel($report);
    }

    public function exportPdf(Request $request, DepartmentReport $report, DepartmentReportExportService $exporter): Response
    {
        $this->authorize('view', $report);
        AuditLog::record($request->user(), 'report.export_pdf', 'Reports', "Ekspor PDF laporan {$report->report_code}.");

        return $exporter->exportPdf($report);
    }

    public function downloadAttachment(Request $request, DepartmentReport $report): StreamedResponse
    {
        $this->authorize('view', $report);
        abort_unless($report->attachment_path && Storage::disk('local')->exists($report->attachment_path), 404);

        return Storage::disk('local')->download($report->attachment_path, $report->attachment_name);
    }
}
