<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\Customer;

/** Luật phân quyền khách hàng (coordinator đăng ký tập trung — xem SalesOrderPolicy). */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.customer.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermission('sales.customer.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.customer.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission('sales.customer.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission('sales.customer.manage');
    }
}
