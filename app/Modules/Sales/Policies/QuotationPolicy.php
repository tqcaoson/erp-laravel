<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\Quotation;

/** Luật phân quyền báo giá (coordinator đăng ký tập trung — xem SalesOrderPolicy). */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.quotation.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('sales.quotation.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.quotation.create');
    }

    public function convert(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('sales.order.create') && $quotation->status === 'accepted';
    }
}
