<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Kỳ chạy lương theo tháng (period dạng 'YYYY-MM'). */
class PayrollRun extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    /** Các trạng thái nhân viên được xem phiếu lương của mình. */
    public const VISIBLE_TO_EMPLOYEE_STATUSES = [
        self::STATUS_LOCKED,
        self::STATUS_APPROVED,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'company_id', 'code', 'period', 'status',
        'total_gross', 'total_deductions', 'total_tax',
        'total_insurance', 'total_net', 'locked_at',
    ];

    protected $casts = [
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_insurance' => 'decimal:2',
        'total_net' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Kỳ lương đã khóa (kể cả đã duyệt / đã trả) thì không được tính lại
     * hay sửa — các trạng thái sau khóa đều là trạng thái "đóng" của kỳ.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_LOCKED,
            self::STATUS_APPROVED,
            self::STATUS_PAID,
        ], true);
    }
}
