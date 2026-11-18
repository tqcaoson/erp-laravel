<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Số dư ngày phép của nhân viên trong 1 năm. */
class LeaveBalance extends BaseModel
{
    /** Số ngày phép mặc định mỗi năm (theo luật lao động: 12 ngày/năm). */
    public const DEFAULT_ANNUAL_DAYS = 12;

    protected $fillable = ['company_id', 'employee_id', 'year', 'total_days', 'used_days'];

    protected $casts = [
        'year' => 'integer',
        'total_days' => 'decimal:2',
        'used_days' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Số ngày phép còn lại. */
    public function remainingDays(): float
    {
        return (float) ($this->total_days - $this->used_days);
    }
}
