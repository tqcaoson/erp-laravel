<?php

namespace App\Core\Models;

/**
 * Quy tắc duyệt chứng từ: loại chứng từ + ngưỡng tiền + cấp duyệt.
 * Ví dụ: đơn bán trên 50 triệu cần kế toán trưởng duyệt.
 */
class ApprovalRule extends BaseModel
{
    protected $fillable = [
        'company_id',
        'document_type',
        'min_amount',
        'max_amount',
        'approver_role',
        'level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function logs()
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
