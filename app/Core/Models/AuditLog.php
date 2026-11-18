<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nhật ký kiểm tra (audit log). Chỉ ghi, không sửa/xóa.
 * Không dùng trait Auditable để tránh đệ quy vô hạn.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
