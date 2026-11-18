<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Công ty (đơn vị hạch toán) trong hệ thống đa công ty.
 * Theo đúng DDL mục 5.3 của bản thiết kế, bổ sung thêm địa chỉ/liên hệ.
 */
class Company extends BaseModel
{
    use HasFactory;

    protected bool $companyScoped = false;

    protected $fillable = [
        'name',
        'tax_code',
        'base_currency',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'company_user');
    }
}
