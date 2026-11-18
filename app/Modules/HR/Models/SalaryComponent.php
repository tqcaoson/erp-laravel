<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/**
 * Thành phần lương: phụ cấp (allowance) hoặc khấu trừ (deduction).
 * calculation = fixed: số tiền cố định; percent: % lương cơ bản.
 */
class SalaryComponent extends BaseModel
{
    public const TYPE_ALLOWANCE = 'allowance';
    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'calculation', 'amount', 'is_active'];

    protected $casts = ['amount' => 'decimal:2', 'is_active' => 'boolean'];

    /** Tính số tiền của thành phần này trên 1 mức lương cơ bản. */
    public function amountFor(float $baseSalary): float
    {
        return $this->calculation === 'percent'
            ? round($baseSalary * (float) $this->amount / 100, 2)
            : (float) $this->amount;
    }
}
