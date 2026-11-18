<?php

namespace App\Modules\Manufacturing\Policies;

use App\Models\User;
use App\Modules\Manufacturing\Models\Bom;

/** Luật phân quyền định mức BOM (coordinator đăng ký tập trung). */
class BomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manufacturing.bom.view');
    }

    public function view(User $user, Bom $bom): bool
    {
        return $user->hasPermission('manufacturing.bom.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manufacturing.bom.manage');
    }

    public function update(User $user, Bom $bom): bool
    {
        return $user->hasPermission('manufacturing.bom.manage');
    }
}
