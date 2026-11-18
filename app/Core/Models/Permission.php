<?php

namespace App\Core\Models;

/**
 * Quyền chức năng dạng "module.action", ví dụ: sales.order.create,
 * accounting.entry.post. Quyền được gán cho vai trò, vai trò gán cho user.
 */
class Permission extends BaseModel
{
    protected bool $companyScoped = false;

    protected $fillable = ['name', 'label', 'module'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
