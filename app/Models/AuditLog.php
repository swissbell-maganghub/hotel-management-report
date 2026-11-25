<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'module',
        'description',
        'ip_address',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        ?User $user,
        string $event,
        string $module,
        string $description,
        array $metadata = []
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'event' => $event,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
