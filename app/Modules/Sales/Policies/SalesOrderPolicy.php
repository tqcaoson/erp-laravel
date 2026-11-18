<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesOrder;

/**
 * Luật phân quyền đơn bán hàng (theo RolePermissionSeeder).
 *
 * GHI CHÚ: theo hợp đồng mục 8, Policy không tự đăng ký được từ module
 * (ModuleServiceProvider không đăng ký policy, không sửa được file dùng chung)
 * — coordinator sẽ đăng ký tập trung ở giai đoạn tích hợp. Route hiện tại
 * đã chặn bằng middleware 'permission:...' nên luật vẫn được áp dụng.
 */
class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.order.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.order.create');
    }

    public function confirm(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.order.confirm') && $order->status === 'draft';
    }

    public function approve(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.order.approve') && $order->status === 'pending_approval';
    }

    public function cancel(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.order.confirm')
            && in_array($order->status, ['draft', 'pending_approval', 'confirmed'], true);
    }
}
