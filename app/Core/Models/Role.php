<?php

namespace App\Core\Models;

/** Vai trò (role) trong mô hình RBAC, ví dụ: admin, ke_toan, kho. */
class Role extends BaseModel
{
    protected bool $companyScoped = false;

    protected $fillable = ['name', 'label', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'role_user');
    }
}
