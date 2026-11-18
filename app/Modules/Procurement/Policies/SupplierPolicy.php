<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\Supplier;

/** Luật phân quyền nhà cung cấp. */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('procurement.supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.supplier.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('procurement.supplier.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('procurement.supplier.manage');
    }
}
