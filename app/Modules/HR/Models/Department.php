<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Phòng ban. Lưu ý: manager_id KHÔNG có FK (employees tạo sau). */
class Department extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'manager_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /** Người quản lý — quan hệ logic, không có FK ở DB. */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
