<?php

// [WASTAGE-REPORT] Model for Wastage Report
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WastageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_code',
        'period_month',
        'total_wastage_kg',
        'total_cover',
        'kg_per_cover',
        'ytd_kg',
        'ytd_cover',
        'original_filename',
        'file_hash_sha256',
        'source_type',
        'imported_by',
        'imported_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_wastage_kg' => 'decimal:2',
            'total_cover' => 'integer',
            'kg_per_cover' => 'decimal:4',
            'ytd_kg' => 'decimal:2',
            'ytd_cover' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(WastageRecord::class)->orderBy('record_date');
    }

    /**
     * Hitung ulang total laporan dari record-record yang tersimpan
     */
    public function recalculateTotals(): void
    {
        $records = $this->records()->orderBy('record_date')->get();

        $totalWastage = 0.0;
        $totalCover = 0;

        foreach ($records as $rec) {
            $totalWastage += (float) $rec->total_kg;
            $totalCover += (int) $rec->covers;
        }

        $this->total_wastage_kg = round($totalWastage, 2);
        $this->total_cover = $totalCover;
        $this->kg_per_cover = $totalCover > 0 ? round($totalWastage / $totalCover, 4) : 0;
        
        $lastRec = $records->last();
        if ($lastRec) {
            $this->ytd_kg = $lastRec->ytd_kg;
            $this->ytd_cover = $lastRec->ytd_cover;
        }

        $this->save();
    }
}
