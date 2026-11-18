<?php

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Invoice;

/**
 * Luật phân quyền hóa đơn (theo tên quyền trong RolePermissionSeeder).
 * Lưu ý: coordinator đăng ký tập trung ở giai đoạn tích hợp; hiện tại
 * route đã chặn bằng middleware 'permission:...'.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.invoice.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoice.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.invoice.create');
    }

    public function post(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoice.post')
            && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoice.post')
            && $invoice->status === Invoice::STATUS_DRAFT;
    }
}
