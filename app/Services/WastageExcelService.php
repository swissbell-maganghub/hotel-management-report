<?php

// [WASTAGE-REPORT] Service for handling Excel import & preview for Wastage Reports
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageReport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WastageExcelService
{
    /**
     * Membaca dan mem-parsing file Excel Wastage harian untuk preview
     */
    // [WASTAGE-REPORT] FIX: periode bulan otomatis dideteksi dari tanggal di file Excel
    public function parseForPreview(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $fileHash = hash_file('sha256', $realPath);
        $duplicateReport = WastageReport::where('file_hash_sha256', $fileHash)->first();

        $spreadsheet = IOFactory::load($realPath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 4) {
            return [
                'file_hash' => $fileHash,
                'is_duplicate' => (bool) $duplicateReport,
                'duplicate_code' => $duplicateReport?->report_code,
                'rows' => [],
                'valid_count' => 0,
                'error_count' => 1,
                'total_wastage_kg' => 0,
                'total_cover' => 0,
                'kg_per_cover' => 0,
                'errors' => ['File Excel tidak memiliki baris data (data harus mulai dari baris ke-4).'],
            ];
        }

        $rows = [];
        $errors = [];
        $validCount = 0;
        $errorCount = 0;
        $totalWastageKg = 0.0;
        $totalCover = 0;

        // Data mulai dari baris 4
        for ($r = 4; $r <= $highestRow; $r++) {
            $rawDate = $sheet->getCell("A{$r}")->getValue();
            if ($rawDate === null || trim((string)$rawDate) === '') {
                continue;
            }

            // Parsing tanggal
            $parsedDate = null;
            try {
                if (is_numeric($rawDate)) {
                    $parsedDate = Carbon::instance(Date::excelToDateTimeObject((float) $rawDate))->format('Y-m-d');
                } else {
                    $parsedDate = Carbon::parse($rawDate)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                $parsedDate = null;
            }

            $rowErrors = [];
            if (!$parsedDate) {
                $rowErrors[] = "Baris {$r}: Format tanggal tidak valid ({$rawDate})";
            }

            $bLunch = (float)($sheet->getCell("B{$r}")->getValue() ?? 0);
            $bDinner = (float)($sheet->getCell("C{$r}")->getValue() ?? 0);
            $bGravity = (float)($sheet->getCell("D{$r}")->getValue() ?? 0);
            $pButcher = (float)($sheet->getCell("E{$r}")->getValue() ?? 0);
            $pMain = (float)($sheet->getCell("F{$r}")->getValue() ?? 0);
            $pPastry = (float)($sheet->getCell("G{$r}")->getValue() ?? 0);
            $pCold = (float)($sheet->getCell("H{$r}")->getValue() ?? 0);
            $plateWaste = (float)($sheet->getCell("I{$r}")->getValue() ?? 0);
            $bBreakfast = (float)($sheet->getCell("J{$r}")->getValue() ?? 0);
            $covers = (int)($sheet->getCell("L{$r}")->getValue() ?? 0);

            $calculatedTotal = round($bLunch + $bDinner + $bGravity + $pButcher + $pMain + $pPastry + $pCold + $plateWaste + $bBreakfast, 2);

            $ytdKg = (float)($sheet->getCell("M{$r}")->getValue() ?? 0);
            $ytdCover = (int)($sheet->getCell("N{$r}")->getValue() ?? 0);
            $kgPerCover = $covers > 0 ? round($calculatedTotal / $covers, 4) : 0;

            if (!empty($rowErrors)) {
                $errorCount++;
                $errors = array_merge($errors, $rowErrors);
            } else {
                $validCount++;
                $totalWastageKg += $calculatedTotal;
                $totalCover += $covers;
            }

            $rows[] = [
                'row_number' => $r,
                'date' => $parsedDate ?? (string)$rawDate,
                'buffet_lunch' => $bLunch,
                'buffet_dinner' => $bDinner,
                'buffet_gravity' => $bGravity,
                'prep_butcher' => $pButcher,
                'prep_main_kitchen' => $pMain,
                'prep_pastry' => $pPastry,
                'prep_cold_kitchen' => $pCold,
                'plate_waste' => $plateWaste,
                'breakfast_cafe' => $bBreakfast,
                'total_kg' => $calculatedTotal,
                'covers' => $covers,
                'ytd_kg' => $ytdKg,
                'ytd_cover' => $ytdCover,
                'kg_per_cover' => $kgPerCover,
                'is_valid' => empty($rowErrors),
                'errors' => $rowErrors,
            ];
        }

        $overallKgPerCover = $totalCover > 0 ? round($totalWastageKg / $totalCover, 4) : 0;

        // [WASTAGE-REPORT] FIX: Periode bulan diambil dari tanggal yang paling dominan di file Excel.
        $periodMonth = collect($rows)
            ->filter(fn ($row) => $row['is_valid'])
            ->map(fn ($row) => substr($row['date'], 0, 7))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'file_hash' => $fileHash,
            'original_filename' => $file->getClientOriginalName(),
            'period_month' => $periodMonth,
            'is_duplicate' => (bool) $duplicateReport,
            'duplicate_code' => $duplicateReport?->report_code,
            'rows' => $rows,
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'total_wastage_kg' => round($totalWastageKg, 2),
            'total_cover' => $totalCover,
            'kg_per_cover' => $overallKgPerCover,
            'errors' => $errors,
        ];
    }

    /**
     * Menyimpan hasil preview Excel ke database
     */
    public function commitPreview(array $preview, User $user, ?string $notes = null): WastageReport
    {
        return DB::transaction(function () use ($preview, $user, $notes) {
            $periodMonth = $preview['period_month'];
            $ym = str_replace('-', '', $periodMonth);

            // Generate report code: WST-YYYYMM-001
            $latest = WastageReport::where('report_code', 'like', "WST-{$ym}-%")
                ->orderByDesc('id')
                ->value('report_code');
            $seq = 1;
            if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
                $seq = (int) $m[1] + 1;
            }
            $code = sprintf('WST-%s-%03d', $ym, $seq);

            $lastRecord = !empty($preview['rows']) ? end($preview['rows']) : null;
            $ytdKg = $lastRecord ? ($lastRecord['ytd_kg'] ?: $preview['total_wastage_kg']) : $preview['total_wastage_kg'];
            $ytdCover = $lastRecord ? ($lastRecord['ytd_cover'] ?: $preview['total_cover']) : $preview['total_cover'];

            $report = WastageReport::create([
                'report_code' => $code,
                'period_month' => $periodMonth,
                'total_wastage_kg' => $preview['total_wastage_kg'],
                'total_cover' => $preview['total_cover'],
                'kg_per_cover' => $preview['kg_per_cover'],
                'ytd_kg' => $ytdKg,
                'ytd_cover' => $ytdCover,
                'original_filename' => $preview['original_filename'] ?? 'uploaded_wastage.xlsx',
                'file_hash_sha256' => $preview['file_hash'],
                'source_type' => 'excel_import',
                'imported_by' => $user->id,
                'imported_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($preview['rows'] as $r) {
                if (!$r['is_valid']) continue;
                WastageRecord::create([
                    'wastage_report_id' => $report->id,
                    'record_date' => $r['date'],
                    'buffet_lunch' => $r['buffet_lunch'],
                    'buffet_dinner' => $r['buffet_dinner'],
                    'buffet_gravity' => $r['buffet_gravity'],
                    'prep_butcher' => $r['prep_butcher'],
                    'prep_main_kitchen' => $r['prep_main_kitchen'],
                    'prep_pastry' => $r['prep_pastry'],
                    'prep_cold_kitchen' => $r['prep_cold_kitchen'],
                    'plate_waste' => $r['plate_waste'],
                    'breakfast_cafe' => $r['breakfast_cafe'],
                    'total_kg' => $r['total_kg'],
                    'covers' => $r['covers'],
                    'ytd_kg' => $r['ytd_kg'],
                    'ytd_cover' => $r['ytd_cover'],
                    'kg_per_cover' => $r['kg_per_cover'],
                ]);
            }

            AuditLog::record(
                $user,
                'wastage_report.import',
                'Wastage Report',
                "Laporan Wastage {$report->report_code} berhasil diimpor ({$report->records()->count()} hari, {$report->total_wastage_kg} KG, {$report->total_cover} Cover)."
            );

            return $report->load(['importer', 'records']);
        });
    }

    /**
     * Download template Excel resmi Wastage Report
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Food Waste Daily');

        $ws->setCellValue('A1', 'FOOD WASTE SWISS-BELHOTEL WATERFRONT MAKASSAR');
        $ws->mergeCells('A1:O1');

        $ws->setCellValue('A2', 'Date');
        $ws->setCellValue('B2', 'Buffet Lunch, Dinner, Gravity');
        $ws->setCellValue('E2', 'Preparation');
        $ws->setCellValue('I2', 'Plate Waste');
        $ws->setCellValue('J2', 'Breakfast Swiss Café');
        $ws->setCellValue('K2', 'TOTAL KG/Day');
        $ws->setCellValue('L2', 'COVER');
        $ws->setCellValue('M2', 'KG YTD');
        $ws->setCellValue('N2', 'COVER YTD');
        $ws->setCellValue('O2', 'KG/COVER');

        $subHeaders = [
            'B3' => 'Lunch', 'C3' => 'Dinner', 'D3' => 'Gravity',
            'E3' => 'Butcher', 'F3' => 'Main Kitchen', 'G3' => 'Pastry', 'H3' => 'Cold Kitchen'
        ];
        foreach ($subHeaders as $cell => $lbl) {
            $ws->setCellValue($cell, $lbl);
        }

        foreach (['A2:A3', 'B2:D2', 'E2:H2', 'I2:I3', 'J2:J3', 'K2:K3', 'L2:L3', 'M2:M3', 'N2:N3', 'O2:O3'] as $range) {
            $ws->mergeCells($range);
        }

        // Contoh baris 4
        $ws->setCellValue('A4', '2026-10-01');
        $ws->setCellValue('B4', 1);
        $ws->setCellValue('C4', 0);
        $ws->setCellValue('D4', 1);
        $ws->setCellValue('E4', 0);
        $ws->setCellValue('F4', 6);
        $ws->setCellValue('G4', 10);
        $ws->setCellValue('H4', 2);
        $ws->setCellValue('I4', 1);
        $ws->setCellValue('J4', 3);
        $ws->setCellValue('K4', '=SUM(B4:J4)');
        $ws->setCellValue('L4', 668);
        $ws->setCellValue('M4', 24);
        $ws->setCellValue('N4', 668);
        $ws->setCellValue('O4', '=IF(L4=0,0,K4/L4)');

        // Styling
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A1:O3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getStyle('A2:O3')->getFont()->setBold(true);
        $ws->getStyle('A2:O3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $ws->getStyle('A2:O4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'O') as $c) {
            $ws->getColumnDimension($c)->setWidth(14);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Template_Wastage_Report_Daily.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Wastage Report ke Excel
     */
    public function exportReport(WastageReport $report): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle($report->report_code);

        $ws->setCellValue('A1', 'FOOD WASTE SWISS-BELHOTEL WATERFRONT MAKASSAR');
        $ws->mergeCells('A1:O1');

        $ws->setCellValue('A2', 'Date');
        $ws->setCellValue('B2', 'Buffet Lunch, Dinner, Gravity');
        $ws->setCellValue('E2', 'Preparation');
        $ws->setCellValue('I2', 'Plate Waste');
        $ws->setCellValue('J2', 'Breakfast Swiss Café');
        $ws->setCellValue('K2', 'TOTAL KG/Day');
        $ws->setCellValue('L2', 'COVER');
        $ws->setCellValue('M2', 'KG YTD');
        $ws->setCellValue('N2', 'COVER YTD');
        $ws->setCellValue('O2', 'KG/COVER');

        $subHeaders = [
            'B3' => 'Lunch', 'C3' => 'Dinner', 'D3' => 'Gravity',
            'E3' => 'Butcher', 'F3' => 'Main Kitchen', 'G3' => 'Pastry', 'H3' => 'Cold Kitchen'
        ];
        foreach ($subHeaders as $cell => $lbl) {
            $ws->setCellValue($cell, $lbl);
        }

        foreach (['A2:A3', 'B2:D2', 'E2:H2', 'I2:I3', 'J2:J3', 'K2:K3', 'L2:L3', 'M2:M3', 'N2:N3', 'O2:O3'] as $range) {
            $ws->mergeCells($range);
        }

        $rowIdx = 4;
        foreach ($report->records as $rec) {
            $ws->setCellValue("A{$rowIdx}", $rec->record_date->format('Y-m-d'));
            $ws->setCellValue("B{$rowIdx}", (float)$rec->buffet_lunch);
            $ws->setCellValue("C{$rowIdx}", (float)$rec->buffet_dinner);
            $ws->setCellValue("D{$rowIdx}", (float)$rec->buffet_gravity);
            $ws->setCellValue("E{$rowIdx}", (float)$rec->prep_butcher);
            $ws->setCellValue("F{$rowIdx}", (float)$rec->prep_main_kitchen);
            $ws->setCellValue("G{$rowIdx}", (float)$rec->prep_pastry);
            $ws->setCellValue("H{$rowIdx}", (float)$rec->prep_cold_kitchen);
            $ws->setCellValue("I{$rowIdx}", (float)$rec->plate_waste);
            $ws->setCellValue("J{$rowIdx}", (float)$rec->breakfast_cafe);
            $ws->setCellValue("K{$rowIdx}", "=SUM(B{$rowIdx}:J{$rowIdx})");
            $ws->setCellValue("L{$rowIdx}", (int)$rec->covers);
            $ws->setCellValue("M{$rowIdx}", (float)$rec->ytd_kg);
            $ws->setCellValue("N{$rowIdx}", (int)$rec->ytd_cover);
            $ws->setCellValue("O{$rowIdx}", "=IF(L{$rowIdx}=0,0,K{$rowIdx}/L{$rowIdx})");
            $rowIdx++;
        }

        $lastRow = max(4, $rowIdx - 1);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle("A1:O3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getStyle('A2:O3')->getFont()->setBold(true);
        $ws->getStyle('A2:O3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $ws->getStyle("A2:O{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'O') as $c) {
            $ws->getColumnDimension($c)->setWidth(14);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "{$report->report_code}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
