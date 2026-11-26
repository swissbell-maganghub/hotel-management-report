<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomecRecord extends Model
{
    /** Satu sumber untuk jenis utilitas: label, satuan, prefix kode batch, ada biaya di file sumber. */
    public const TYPES = [
        'electricity' => ['id' => 'Listrik', 'en' => 'Electricity', 'unit' => 'kWh', 'prefix' => 'ELC', 'cost' => true],
        'water' => ['id' => 'Air', 'en' => 'Water', 'unit' => 'm³', 'prefix' => 'WTR', 'cost' => true],
        'gas' => ['id' => 'Gas LPG', 'en' => 'LPG Gas', 'unit' => 'tabung', 'prefix' => 'GAS', 'cost' => false],
        'solar' => ['id' => 'Solar Genset', 'en' => 'Genset Diesel', 'unit' => 'liter', 'prefix' => 'SOL', 'cost' => false],
    ];

    protected $fillable = [
        'pomec_import_id',
        'utility_type',
        'reading_date',
        'meter_name',
        'lwbp_value',
        'wbp_value',
        'water_m3',
        'total_usage',
        'cost',
        'transaction_hash',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'lwbp_value' => 'decimal:2',
            'wbp_value' => 'decimal:2',
            'water_m3' => 'decimal:2',
            'total_usage' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public static function unitFor(string $type): string
    {
        return self::TYPES[$type]['unit'] ?? '';
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PomecImport::class, 'pomec_import_id');
    }
}
