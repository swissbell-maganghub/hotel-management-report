<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomecRecord extends Model
{
    protected $fillable = [
        'pomec_import_id',
        'utility_type',
        'reading_date',
        'meter_name',
        'lwbp_value',
        'wbp_value',
        'water_m3',
        'total_usage',
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
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PomecImport::class, 'pomec_import_id');
    }
}
