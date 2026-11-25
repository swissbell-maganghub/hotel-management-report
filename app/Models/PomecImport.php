<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PomecImport extends Model
{
    protected $fillable = [
        'batch_code',
        'utility_type',
        'period_month',
        'original_filename',
        'file_hash_sha256',
        'stored_path',
        'total_rows',
        'total_usage',
        'status',
        'uploaded_by',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_usage' => 'decimal:2',
            'committed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(PomecRecord::class)->orderBy('reading_date');
    }
}
