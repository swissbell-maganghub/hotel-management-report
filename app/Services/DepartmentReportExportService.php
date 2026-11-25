<?php

namespace App\Services;

use App\Models\DepartmentReport;
use App\Support\ExcelCell;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentReportExportService
{
    public function exportExcel(DepartmentReport $report): StreamedResponse
    {
        $report->loadMissing(['department', 'template', 'creator', 'reviewer']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($report->department->code.' Report', 0, 31));

        $sheet->setCellValue('A1', 'SWISS-BELHOTEL WATERFRONT MAKASSAR');
        $sheet->setCellValue('A2', ExcelCell::sanitize($report->title).' ('.$report->report_code.')');
        $sheet->setCellValue('A3', 'Departemen: '.ExcelCell::sanitize($report->department->name).' | Periode: '.$report->period_date->format('d M Y').' | Status: '.strtoupper($report->status));
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);

        $columns = $report->template->columns_schema ?? [];
        $headers = array_map(fn (array $col) => ExcelCell::sanitize((string) ($col['label'] ?? $col['key'])), $columns);
        $sheet->fromArray($headers, null, 'A5');

        $lastColIndex = max(1, count($headers));
        $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);

        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2942']],
        ]);

        $rowNum = 6;
        foreach ($report->rows_data ?? [] as $row) {
            $line = [];
            foreach ($columns as $col) {
                $val = $row[$col['key']] ?? '';
                if (in_array($col['type'] ?? 'text', ['number', 'currency'], true) && is_numeric($val)) {
                    $line[] = (float) $val;
                } else {
                    $line[] = ExcelCell::sanitize((string) $val);
                }
            }
            $sheet->fromArray($line, null, "A{$rowNum}");
            $rowNum++;
        }

        for ($i = 1; $i <= $lastColIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = strtolower($report->report_code).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(DepartmentReport $report): Response
    {
        $report->loadMissing(['department', 'template', 'creator', 'reviewer', 'histories.user']);

        $pdf = Pdf::loadView('exports.department-report-pdf', [
            'report' => $report,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(strtolower($report->report_code).'.pdf');
    }
}
