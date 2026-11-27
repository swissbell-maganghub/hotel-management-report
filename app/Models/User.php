<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'is_active', 'is_read_only', 'locale', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_GM = 'gm';

    public const ROLE_HOD = 'hod';

    public const ROLE_STAFF = 'staff';

    public const ROLE_IT_ADMIN = 'it_admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_read_only' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function extraPermissions(): HasMany
    {
        return $this->hasMany(UserDepartmentPermission::class);
    }

    public function isGm(): bool
    {
        return $this->role === self::ROLE_GM;
    }

    public function isHod(): bool
    {
        return $this->role === self::ROLE_HOD;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isItAdmin(): bool
    {
        return $this->role === self::ROLE_IT_ADMIN;
    }

    public function canViewExecutiveDashboard(): bool
    {
        return $this->isGm() || $this->isItAdmin();
    }

    public function canAccessAllDepartments(): bool
    {
        return $this->isGm() || $this->isItAdmin();
    }

    public function accessLevelForDepartment(?int $departmentId): ?string
    {
        if ($this->isItAdmin()) {
            return UserDepartmentPermission::LEVEL_REVIEW;
        }

        if ($this->isGm()) {
            return UserDepartmentPermission::LEVEL_READ;
        }

        if (! $departmentId) {
            return null;
        }

        if ($this->department_id === $departmentId) {
            if ($this->is_read_only) {
                return UserDepartmentPermission::LEVEL_READ;
            }

            return $this->isHod()
                ? UserDepartmentPermission::LEVEL_REVIEW
                : UserDepartmentPermission::LEVEL_INPUT;
        }

        $extra = $this->extraPermissions->firstWhere('department_id', $departmentId);

        return $extra?->access_level;
    }

    public function accessibleDepartmentIds(): array
    {
        if ($this->canAccessAllDepartments()) {
            return Department::where('is_active', true)->pluck('id')->all();
        }

        $extraIds = $this->extraPermissions->pluck('department_id')->all();

        return array_values(array_unique(array_filter(array_merge([$this->department_id], $extraIds))));
    }

    public function inputtableDepartmentIds(): array
    {
        if ($this->isItAdmin()) {
            return Department::where('is_active', true)->pluck('id')->all();
        }

        if ($this->isGm()) {
            return [];
        }

        return array_values(array_filter(
            $this->accessibleDepartmentIds(),
            fn (int $deptId): bool => $this->canInputDepartment($deptId)
        ));
    }

    public function reviewableDepartmentIds(): array
    {
        if ($this->isItAdmin()) {
            return Department::where('is_active', true)->pluck('id')->all();
        }

        if ($this->isGm()) {
            return [];
        }

        return array_values(array_filter(
            $this->accessibleDepartmentIds(),
            fn (int $deptId): bool => $this->canReviewDepartment($deptId)
        ));
    }

    public function canViewDepartment(?int $departmentId): bool
    {
        return $this->accessLevelForDepartment($departmentId) !== null;
    }

    public function canInputDepartment(?int $departmentId): bool
    {
        return in_array(
            $this->accessLevelForDepartment($departmentId),
            [UserDepartmentPermission::LEVEL_INPUT, UserDepartmentPermission::LEVEL_REVIEW],
            true
        );
    }

    public function canReviewDepartment(?int $departmentId): bool
    {
        return $this->accessLevelForDepartment($departmentId) === UserDepartmentPermission::LEVEL_REVIEW;
    }

    public function canCreateReports(): bool
    {
        if (! config('hotel.manual_reports')) {
            return false;
        }

        return $this->isItAdmin() || count($this->inputtableDepartmentIds()) > 0;
    }

    public function canReviewReports(): bool
    {
        return $this->isItAdmin() || count($this->reviewableDepartmentIds()) > 0;
    }

    /**
     * System Topology menu: IT Admin (full control) and General Manager (read-only summary).
     */
    public function canViewTopology(): bool
    {
        return $this->isItAdmin() || $this->isGm();
    }

    /**
     * Edit components & run live health checks: IT Admin only.
     */
    public function canEditTopology(): bool
    {
        return $this->isItAdmin();
    }

    public function canManageUsers(): bool
    {
        return $this->isItAdmin();
    }

    public function canAccessPomec(): bool
    {
        if ($this->canAccessAllDepartments()) {
            return true;
        }

        $engId = Department::where('code', 'ENG')->value('id');

        return $engId ? $this->canViewDepartment((int) $engId) : false;
    }

    public function canModifyPomec(): bool
    {
        if ($this->isItAdmin()) {
            return true;
        }

        if ($this->isGm()) {
            return false;
        }

        $engId = Department::where('code', 'ENG')->value('id');

        return $engId ? $this->canInputDepartment((int) $engId) : false;
    }

    // [WASTAGE-REPORT] Akses ke Wastage Report (Executive & staff F&B)
    public static function fnbDepartmentId(): ?int
    {
        return Department::where('code', 'FNB')->value('id');
    }

    public function canAccessWastage(): bool
    {
        if ($this->canAccessAllDepartments()) {
            return true;
        }

        $fnbId = self::fnbDepartmentId();
        return $fnbId ? $this->canViewDepartment((int) $fnbId) : false;
    }

    public function canModifyWastage(): bool
    {
        if ($this->isItAdmin()) {
            return true;
        }

        if ($this->isGm()) {
            return false;
        }

        $fnbId = self::fnbDepartmentId();
        return $fnbId ? $this->canInputDepartment((int) $fnbId) : false;
    }

    /**
     * Hapus batch impor POMEC: level review di Engineering (HOD Engineering dan IT Admin).
     */
    public function canDeletePomec(): bool
    {
        $engId = Department::where('code', 'ENG')->value('id');

        return $engId ? $this->canReviewDepartment((int) $engId) : false;
    }

    public function roleLabel(string $locale = 'id'): string
    {
        return match ($this->role) {
            self::ROLE_GM => 'General Manager',
            self::ROLE_HOD => $locale === 'en' ? 'Head of Department' : 'Head of Department (HOD)',
            self::ROLE_STAFF => $locale === 'en' ? 'Admin / Staff' : 'Admin / Staf',
            self::ROLE_IT_ADMIN => 'IT Administrator',
            default => strtoupper($this->role),
        };
    }

    public function scopeLabel(string $locale = 'id'): string
    {
        if ($this->isGm()) {
            return $locale === 'en' ? 'All Departments' : 'Semua Departemen';
        }

        if ($this->isItAdmin()) {
            return $locale === 'en' ? 'System & All Depts (Full)' : 'Sistem & Semua Dept (Penuh)';
        }

        $base = $this->department?->localizedName($locale) ?? ($locale === 'en' ? 'Unassigned' : 'Belum Diatur');
        $extraCount = $this->relationLoaded('extraPermissions')
            ? $this->extraPermissions->count()
            : $this->extraPermissions()->count();

        if ($extraCount > 0) {
            return $base.' (+'.$extraCount.' Dept)';
        }

        return $base;
    }
}
