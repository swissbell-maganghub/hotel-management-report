<?php

namespace App\Http\Controllers;

use App\Models\PomecRecord;
use App\Models\User;
use App\Services\PomecExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'utility_type' => ['required', 'in:electricity,water'],
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
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

            return response()->json([
                'message' => "Impor {$import->batch_code} berhasil disimpan ({$import->total_rows} baris).",
                'import' => $import->load(['uploader', 'records']),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function template(Request $request, string $utilityType, PomecExcelService $service): StreamedResponse
    {
        abort_unless($request->user()->canAccessPomec(), 403);
        abort_unless(in_array($utilityType, ['electricity', 'water'], true), 404);

        return $service->downloadSampleTemplate($utilityType);
    }

    public function exportExcel(Request $request, string $utilityType, PomecExcelService $service): StreamedResponse
    {
        abort_unless($request->user()->canAccessPomec(), 403);
        abort_unless(in_array($utilityType, ['electricity', 'water'], true), 404);

        return $service->exportExcel($utilityType, $request->query('period_month'));
    }

    public function exportPdf(Request $request, string $utilityType): Response
    {
        abort_unless($request->user()->canAccessPomec(), 403);
        abort_unless(in_array($utilityType, ['electricity', 'water'], true), 404);

        $records = PomecRecord::with('import')
            ->where('utility_type', $utilityType)
            ->orderBy('reading_date')
            ->get();

        $pdf = Pdf::loadView('exports.pomec-pdf', [
            'utilityType' => $utilityType,
            'records' => $records,
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("pomec_{$utilityType}_report.pdf");
    }
}
