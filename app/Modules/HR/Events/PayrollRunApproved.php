<?php

namespace App\Modules\HR\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Bảng lương đã được duyệt (sau bước khóa).
 * Listener: Accounting sinh bút toán lương (chi phí lương / phải trả NLĐ).
 */
class PayrollRunApproved
{
    use Dispatchable;

    /**
     * @param  array{gross: float, allowances: float, deductions: float, insurance_employee: float, insurance_employer: float, tax: float, net: float} $totals
     */
    public function __construct(
        public int $payrollRunId,
        public int $companyId,
        public string $period, // 'YYYY-MM'
        public array $totals,
    ) {}
}
