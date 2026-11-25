<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_REVIEWED = 'reviewed';

    protected $fillable = [
        'report_code',
        'department_id',
        'report_template_id',
        'title',
        'period_type',
        'period_date',
        'status',
        'rows_data',
        'summary_notes',
        'attachment_path',
        'attachment_name',
        'created_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'rows_data' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReportHistory::class)->latest('created_at');
    }

    public function isEditableBy(User $user): bool
    {
        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true)) {
            return false;
        }

        return $user->canInputDepartment($this->department_id);
    }

    public function isReviewableBy(User $user): bool
    {
        if (! in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_IN_REVIEW], true)) {
            return false;
        }

        return $user->canReviewDepartment($this->department_id);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->canViewDepartment($this->department_id);
    }
}
