<?php

namespace App\Http\Controllers;

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
        ]);

        $preview = $service->parseForPreview(
            $request->file('excel_file'),
            $validated['utility_type'],
            $validated['period_month']
        );

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
        ]);

        $sessionKey = "pomec_preview_{$data['preview_token']}";
        $preview = session($sessionKey);
        if (! $preview) {
            return response()->json(['message' => 'Sesi preview kedaluwarsa. Silakan unggah ulang file Excel.'], 422);
        }

        try {
            $import = $service->commitPreview($preview, $user);
            session()->forget($sessionKey);

            // Kode batch tetap tercatat di riwayat & audit log; popup cukup bahasa sehari-hari.
            $utility = PomecRecord::TYPES[$import->utility_type]['id'];
            $month = Carbon::parse("{$import->period_month}-01")->locale('id')->translatedFormat('F Y');

            return response()->json([
                'message' => "{$import->total_rows} data {$utility} {$month} tersimpan.",
                'import' => $import->load(['uploader', 'records']),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
