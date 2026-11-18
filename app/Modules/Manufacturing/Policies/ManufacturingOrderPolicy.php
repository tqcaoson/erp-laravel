<?php

namespace App\Modules\Manufacturing\Policies;

use App\Models\User;
use App\Modules\Manufacturing\Models\ManufacturingOrder;

/**
 * Luật phân quyền lệnh sản xuất (theo RolePermissionSeeder).
 * Coordinator đăng ký tập trung ở giai đoạn tích hợp; route đã chặn bằng
 * middleware 'permission:...' nên luật vẫn được áp dụng.
 */
class ManufacturingOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manufacturing.mo.view');
    }

    public function view(User $user, ManufacturingOrder $order): bool
    {
        return $user->hasPermission('manufacturing.mo.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manufacturing.mo.create');
    }

    public function start(User $user, ManufacturingOrder $order): bool
    {
        return $user->hasPermission('manufacturing.mo.start') && $order->status === 'confirmed';
    }

    public function complete(User $user, ManufacturingOrder $order): bool
    {
        return $user->hasPermission('manufacturing.mo.complete') && $order->status === 'in_progress';
    }
}
