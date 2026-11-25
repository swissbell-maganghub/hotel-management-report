<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    protected $fillable = [
        'department_id',
        'code',
        'name',
        'name_en',
        'description',
        'description_en',
        'columns_schema',
        'default_rows',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'columns_schema' => 'array',
            'default_rows' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DepartmentReport::class);
    }

    public function localizedName(string $locale = 'id'): string
    {
        return $locale === 'en' ? $this->name_en : $this->name;
    }

    public function localizedDescription(string $locale = 'id'): ?string
    {
        return $locale === 'en' ? $this->description_en : $this->description;
    }
}
