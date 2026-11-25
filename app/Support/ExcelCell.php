<?php

namespace App\Support;

class ExcelCell
{
    /**
     * Neutralize spreadsheet formula triggers (=, +, -, @, tab, CR) on text cells.
     */
    public static function sanitize(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
