<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\GoodsReceipt;

/** Luật phân quyền phiếu nhận hàng. */
class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.gr.view');
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $user->hasPermission('procurement.gr.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.gr.create');
    }

    public function validate(User $user, GoodsReceipt $receipt): bool
    {
        return $user->hasPermission('procurement.gr.create');
    }
}
