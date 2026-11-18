<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Phiếu lương của 1 nhân viên trong 1 kỳ lương. */
class Payslip extends BaseModel
{
    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id',
        'base_salary', 'allowances', 'deductions',
        'insurance_employee', 'tax', 'net_salary', 'status',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'insurance_employee' => 'decimal:2',
        'tax' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines()
    {
        return $this->hasMany(PayslipLine::class);
    }

    /** Tổng thu nhập trước khấu trừ = lương cơ bản + phụ cấp. */
    public function gross(): float
    {
        return (float) $this->base_salary + (float) $this->allowances;
    }
}
