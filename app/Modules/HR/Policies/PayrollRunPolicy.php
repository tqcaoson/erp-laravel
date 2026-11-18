<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\PayrollRun;

/** Luật phân quyền bảng lương (xem mục 8 hợp đồng về cách đăng ký Policy). */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.view');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $user->hasPermission('hr.payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.payroll.run');
    }

    public function run(User $user, PayrollRun $payrollRun): bool
    {
        return $user->hasPermission('hr.payroll.run');
    }

    public function lock(User $user, PayrollRun $payrollRun): bool
    {
        return $user->hasPermission('hr.payroll.approve');
    }
}
