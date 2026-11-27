<?php

// [WASTAGE-REPORT] Model for Daily Wastage Record
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageRecord extends Model
{
    use HasFactory;

    public const WASTE_FIELDS = [
        'buffet_lunch' => ['category' => 'Buffet', 'label' => 'Lunch'],
        'buffet_dinner' => ['category' => 'Buffet', 'label' => 'Dinner'],
        'buffet_gravity' => ['category' => 'Buffet', 'label' => 'Gravity'],
        'prep_butcher' => ['category' => 'Preparation', 'label' => 'Butcher'],
        'prep_main_kitchen' => ['category' => 'Preparation', 'label' => 'Main Kitchen'],
        'prep_pastry' => ['category' => 'Preparation', 'label' => 'Pastry'],
        'prep_cold_kitchen' => ['category' => 'Preparation', 'label' => 'Cold Kitchen'],
        'plate_waste' => ['category' => 'Plate', 'label' => 'Plate Waste'],
        'breakfast_cafe' => ['category' => 'Breakfast', 'label' => 'Breakfast Swiss Café'],
    ];

    protected $fillable = [
        'wastage_report_id',
        'record_date',
        'buffet_lunch',
        'buffet_dinner',
        'buffet_gravity',
        'prep_butcher',
        'prep_main_kitchen',
        'prep_pastry',
        'prep_cold_kitchen',
        'plate_waste',
        'breakfast_cafe',
        'total_kg',
        'covers',
        'ytd_kg',
        'ytd_cover',
        'kg_per_cover',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'buffet_lunch' => 'decimal:2',
            'buffet_dinner' => 'decimal:2',
            'buffet_gravity' => 'decimal:2',
            'prep_butcher' => 'decimal:2',
            'prep_main_kitchen' => 'decimal:2',
            'prep_pastry' => 'decimal:2',
            'prep_cold_kitchen' => 'decimal:2',
            'plate_waste' => 'decimal:2',
            'breakfast_cafe' => 'decimal:2',
            'total_kg' => 'decimal:2',
            'covers' => 'integer',
            'ytd_kg' => 'decimal:2',
            'ytd_cover' => 'integer',
            'kg_per_cover' => 'decimal:4',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WastageReport::class, 'wastage_report_id');
    }

    public function calculateDailyTotal(): float
    {
        $sum = (float)$this->buffet_lunch +
               (float)$this->buffet_dinner +
               (float)$this->buffet_gravity +
               (float)$this->prep_butcher +
               (float)$this->prep_main_kitchen +
               (float)$this->prep_pastry +
               (float)$this->prep_cold_kitchen +
               (float)$this->plate_waste +
               (float)$this->breakfast_cafe;

        return round($sum, 2);
    }
}
