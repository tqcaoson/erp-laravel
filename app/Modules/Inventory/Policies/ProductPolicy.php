<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Product;

/**
 * Luật phân quyền sản phẩm (dùng quyền trong RolePermissionSeeder).
 * Policy sẽ được coordinator đăng ký tập trung ở giai đoạn tích hợp;
 * hiện tại route đã chặn bằng middleware permission:... (xem routes.php).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.product.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('inventory.product.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.product.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('inventory.product.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('inventory.product.manage');
    }
}
