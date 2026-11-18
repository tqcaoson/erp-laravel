<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\LeaveRequest;

/** Luật phân quyền đơn nghỉ phép (xem mục 8 hợp đồng về cách đăng ký Policy). */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.leave.view');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->hasPermission('hr.leave.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.leave.request');
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->hasPermission('hr.leave.approve');
    }
}
