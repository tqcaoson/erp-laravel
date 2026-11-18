<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\DeliveryOrder;

/** Luật phân quyền phiếu giao hàng (coordinator đăng ký tập trung — xem SalesOrderPolicy). */
class DeliveryOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.delivery.view');
    }

    public function view(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $user->hasPermission('sales.delivery.view');
    }

    public function validate(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $user->hasPermission('sales.delivery.validate') && $deliveryOrder->status === 'draft';
    }
}
