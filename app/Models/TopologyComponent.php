<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopologyComponent extends Model
{
    protected $fillable = [
        'component_key',
        'name',
        'name_en',
        'zone',
        'status',
        'gm_summary',
        'gm_summary_en',
        'it_technical_detail',
        'host_or_endpoint',
        'last_checked_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
        ];
    }
}
