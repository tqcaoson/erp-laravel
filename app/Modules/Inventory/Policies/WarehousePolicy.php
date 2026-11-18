<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;

/** Luật phân quyền kho hàng. */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.warehouse.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('inventory.warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.warehouse.manage');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('inventory.warehouse.manage');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('inventory.warehouse.manage');
    }
}
