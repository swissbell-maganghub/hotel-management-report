<?php

namespace App\Policies;

use App\Models\DepartmentReport;
use App\Models\User;

class DepartmentReportPolicy
{
    public function view(User $user, DepartmentReport $report): bool
    {
        return $report->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->canCreateReports();
    }

    public function update(User $user, DepartmentReport $report): bool
    {
        return $report->isEditableBy($user);
    }

    public function review(User $user, DepartmentReport $report): bool
    {
        return $report->isReviewableBy($user);
    }

    public function delete(User $user, DepartmentReport $report): bool
    {
        return $report->status === DepartmentReport::STATUS_DRAFT
            && $report->isEditableBy($user);
    }
}
