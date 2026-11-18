<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\PurchaseOrder;

/** Luật phân quyền đơn mua hàng. */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.po.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('procurement.po.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.po.create');
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('procurement.po.approve');
    }
}
