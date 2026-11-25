<?php

namespace App\Services;

use App\Models\ReportTemplate;
use Illuminate\Validation\ValidationException;

class DepartmentReportService
{
    /**
     * Validate and sanitize dynamic department report rows against the template's columns_schema.
     *
     * @return array<int, array<string, mixed>>
     */
    public function validateAndSanitizeRows(ReportTemplate $template, mixed $rawRows): array
    {
        if (! is_array($rawRows) || count($rawRows) === 0) {
            throw ValidationException::withMessages([
                'rows_data' => ['Minimal harus ada 1 baris data pada tabel laporan.'],
            ]);
        }

        if (count($rawRows) > 200) {
            throw ValidationException::withMessages([
                'rows_data' => ['Maksimal 200 baris per laporan departemen.'],
            ]);
        }

        $schema = $template->columns_schema ?? [];
        $sanitized = [];

        foreach ($rawRows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'rows_data' => ['Format baris ke-'.($index + 1).' tidak valid.'],
                ]);
            }

            $cleanRow = [];
            $rowNumber = $index + 1;

            foreach ($schema as $col) {
                $key = $col['key'];
                $label = $col['label'] ?? $key;
                $type = $col['type'] ?? 'text';
                $required = (bool) ($col['required'] ?? false);
                $val = $row[$key] ?? null;

                if (in_array($type, ['number', 'currency'], true)) {
                    if ($val === null || $val === '') {
                        if ($required) {
                            throw ValidationException::withMessages([
                                'rows_data' => ["Baris {$rowNumber}: Kolom '{$label}' wajib diisi angka."],
                            ]);
                        }
                        $cleanRow[$key] = 0;
                    } elseif (! is_numeric($val)) {
                        throw ValidationException::withMessages([
                            'rows_data' => ["Baris {$rowNumber}: Kolom '{$label}' harus berupa angka valid."],
                        ]);
                    } else {
                        $cleanRow[$key] = (float) $val;
                    }
                } elseif ($type === 'select') {
                    $strVal = trim((string) $val);
                    $options = $col['options'] ?? [];
                    if ($required && $strVal === '') {
                        throw ValidationException::withMessages([
                            'rows_data' => ["Baris {$rowNumber}: Kolom '{$label}' wajib dipilih."],
                        ]);
                    }
                    if ($strVal !== '' && ! empty($options) && ! in_array($strVal, $options, true)) {
                        throw ValidationException::withMessages([
                            'rows_data' => ["Baris {$rowNumber}: Pilihan '{$strVal}' pada kolom '{$label}' tidak valid."],
                        ]);
                    }
                    $cleanRow[$key] = $strVal;
                } else {
                    $strVal = trim((string) $val);
                    if ($required && $strVal === '') {
                        throw ValidationException::withMessages([
                            'rows_data' => ["Baris {$rowNumber}: Kolom '{$label}' wajib diisi."],
                        ]);
                    }
                    $cleanRow[$key] = mb_substr($strVal, 0, 500);
                }
            }

            $sanitized[] = $cleanRow;
        }

        return $sanitized;
    }
}
