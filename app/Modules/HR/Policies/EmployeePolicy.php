<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;

/**
 * Luật phân quyền nhân viên (dựa trên permission dạng "hr.*").
 * GHI CHÚ: theo mục 8 hợp đồng, Policy được coordinator đăng ký tập
 * trung ở giai đoạn tích hợp; hiện tại route dùng middleware
 * 'permission:...' để chặn truy cập.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.employee.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hr.employee.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.employee.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hr.employee.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hr.employee.manage');
    }
}
