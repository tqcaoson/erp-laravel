<?php

namespace App\Models;

use App\Core\Models\Company;
use App\Core\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Người dùng hệ thống.
 * - Thuộc nhiều công ty qua bảng company_user, công ty đang làm việc
 *   lưu ở current_company_id.
 * - Phân quyền RBAC qua roles -> permissions (dạng "module.action").
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_company_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user');
    }

    public function currentCompany()
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /** Hồ sơ nhân viên gắn với tài khoản này (1 user tối đa 1 nhân viên). */
    public function employee()
    {
        return $this->hasOne(\App\Modules\HR\Models\Employee::class, 'user_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('roles.name', $role)->exists();
    }

    /** Kiểm tra user có quyền chức năng hay không (qua bất kỳ role nào). */
    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('permissions.name', $permission))
            ->exists();
    }

    /** Danh sách tên quyền của user, để frontend ẩn/hiện nút theo quyền. */
    public function permissionNames(): array
    {
        if ($this->hasRole('admin')) {
            return \App\Core\Models\Permission::query()->pluck('name')->all();
        }

        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
