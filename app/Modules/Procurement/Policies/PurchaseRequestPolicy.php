<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\PurchaseRequest;

/** Luật phân quyền yêu cầu mua hàng. */
class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.pr.view');
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $user->hasPermission('procurement.pr.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.pr.create');
    }

    public function approve(User $user, PurchaseRequest $request): bool
    {
        return $user->hasPermission('procurement.pr.approve');
    }
}
