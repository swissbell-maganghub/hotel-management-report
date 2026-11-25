<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'department_id', 'access_level'])]
class UserDepartmentPermission extends Model
{
    public const LEVEL_READ = 'read';

    public const LEVEL_INPUT = 'input';

    public const LEVEL_REVIEW = 'review';

    protected $table = 'department_user_permissions';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
