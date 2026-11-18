<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;

/** Luật phân quyền tồn kho: xem tồn, nhập/xuất/chuyển kho, kiểm kê. */
class StockPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('inventory.stock.view');
    }

    public function move(User $user): bool
    {
        return $user->hasPermission('inventory.stock.move');
    }

    public function count(User $user): bool
    {
        return $user->hasPermission('inventory.stock.count');
    }
}
