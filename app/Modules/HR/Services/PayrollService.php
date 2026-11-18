<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Events\PayrollRunLocked;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryComponent;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ tính lương.
 *
 * CÔNG THỨC (bản demo):
 *   gross = lương cơ bản + phụ cấp
 *   net   = gross − khấu trừ − bảo hiểm phần NLĐ − thuế TNCN
 *
 * LƯU Ý QUAN TRỌNG — TỶ LỆ BẢO HIỂM VÀ THUẾ DƯỚI ĐÂY LÀ BẢN DEMO THEO
 * LUẬT HIỆN HÀNH TẠI THỜI ĐIỂM VIẾT CODE, CẦN KIỂM TRA LẠI VỚI KẾ TOÁN /
 * CHUYÊN GIA THUẾ TRƯỚC KHI DÙNG THẬT:
 *  - BHXH phần NLĐ 8%, BHYT 1,5%, BHTN 1% trên lương cơ bản (chưa áp
 *    trần lương đóng bảo hiểm).
 *  - Thuế TNCN: biểu thuế lũy tiến RÚT GỌN demo (5 bậc, xem TAX_BRACKETS),
 *    giảm trừ bản thân 11.000.000 đồng/tháng, chưa tính giảm trừ gia cảnh
 *    cho người phụ thuộc.
 */
class PayrollService
{
    // Tỷ lệ bảo hiểm phần người lao động (demo — kiểm tra lại khi dùng thật).
    public const BHXH_EMPLOYEE_RATE = 0.08;   // 8%
    public const BHYT_EMPLOYEE_RATE = 0.015;  // 1,5%
    public const BHTN_EMPLOYEE_RATE = 0.01;   // 1%

    // Tỷ lệ bảo hiểm phần người sử dụng lao động (demo — chỉ dùng để báo
    // cáo tổng chi phí trong event PayrollRunLocked, không trừ vào lương NLĐ).
    public const BHXH_EMPLOYER_RATE = 0.175;  // 17,5% (gồm 0,5% TNLĐ-BNN)
    public const BHYT_EMPLOYER_RATE = 0.03;   // 3%
    public const BHTN_EMPLOYER_RATE = 0.01;   // 1%

    /** Giảm trừ bản thân khi tính thuế TNCN (đồng/tháng, demo). */
    public const PERSONAL_DEDUCTION = 11_000_000;

    /**
     * Biểu thuế TNCN lũy tiến RÚT GỌN (demo, 5 bậc).
     * Mỗi bậc: [giới hạn trên của thu nhập tính thuế, thuế suất].
     * Thu nhập tính thuế = gross − khấu trừ − bảo hiểm NLĐ − giảm trừ bản thân.
     */
    public const TAX_BRACKETS = [
        [5_000_000, 0.05],   // đến 5 triệu: 5%
        [10_000_000, 0.10],  // 5–10 triệu: 10%
        [18_000_000, 0.15],  // 10–18 triệu: 15%
        [32_000_000, 0.20],  // 18–32 triệu: 20%
        [PHP_INT_MAX, 0.25], // trên 32 triệu: 25%
    ];

    /**
     * Tính thuế TNCN lũy tiến từng phần theo biểu rút gọn.
     * Ví dụ thu nhập tính thuế 6 triệu: 5tr × 5% + 1tr × 10% = 350.000.
     */
    public function calculateTax(float $taxableIncome): float
    {
        if ($taxableIncome <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $previousLimit = 0;

        foreach (self::TAX_BRACKETS as [$limit, $rate]) {
            if ($taxableIncome <= $previousLimit) {
                break;
            }

            $tax += (min($taxableIncome, $limit) - $previousLimit) * $rate;
            $previousLimit = $limit;
        }

        return round($tax, 2);
    }

    /** Bảo hiểm phần NLĐ trên lương cơ bản (BHXH 8% + BHYT 1,5% + BHTN 1%). */
    public function calculateInsuranceEmployee(float $baseSalary): float
    {
        return round($baseSalary * (self::BHXH_EMPLOYEE_RATE + self::BHYT_EMPLOYEE_RATE + self::BHTN_EMPLOYEE_RATE), 2);
    }

    /** Bảo hiểm phần người sử dụng lao động (chỉ để báo cáo tổng chi phí). */
    public function calculateInsuranceEmployer(float $baseSalary): float
    {
        return round($baseSalary * (self::BHXH_EMPLOYER_RATE + self::BHYT_EMPLOYER_RATE + self::BHTN_EMPLOYER_RATE), 2);
    }

    /**
     * Tính lương cho 1 kỳ lương.
     *
     * - Chỉ tính cho nhân viên đang hoạt động (status active).
     * - Chạy trong transaction; idempotent: kỳ ở trạng thái draft hoặc
     *   calculated sẽ xóa phiếu lương cũ rồi tính lại từ đầu.
     * - Kỳ đã khóa (locked) thì không được tính lại.
     */
    public function calculate(PayrollRun $run): PayrollRun
    {
        if ($run->isLocked()) {
            throw new DomainException('Kỳ lương đã khóa, không thể tính lại.');
        }

        return DB::transaction(function () use ($run) {
            // Idempotent: xóa phiếu lương cũ của kỳ này trước khi tính lại.
            // PayslipLine bị xóa theo qua khóa ngoại cascade.
            $run->payslips()->delete();

            $components = SalaryComponent::where('is_active', true)->get();
            $allowances = $components->where('type', SalaryComponent::TYPE_ALLOWANCE);
            $deductions = $components->where('type', SalaryComponent::TYPE_DEDUCTION);

            $employees = Employee::where('status', Employee::STATUS_ACTIVE)
                ->orderBy('code')
                ->get();

            $totals = [
                'gross' => 0.0, 'allowances' => 0.0, 'deductions' => 0.0,
                'insurance_employee' => 0.0, 'insurance_employer' => 0.0,
                'tax' => 0.0, 'net' => 0.0,
            ];

            foreach ($employees as $employee) {
                $base = (float) $employee->base_salary;

                // Phụ cấp: fixed hoặc % lương cơ bản.
                $allowanceTotal = 0.0;
                $lines = [];
                foreach ($allowances as $component) {
                    $amount = $component->amountFor($base);
                    $allowanceTotal += $amount;
                    $lines[] = [
                        'component_code' => $component->code,
                        'label' => $component->name,
                        'amount' => $amount,
                        'type' => 'earning',
                    ];
                }

                $gross = $base + $allowanceTotal;

                // Khấu trừ: fixed hoặc % lương cơ bản.
                $deductionTotal = 0.0;
                foreach ($deductions as $component) {
                    $amount = $component->amountFor($base);
                    $deductionTotal += $amount;
                    $lines[] = [
                        'component_code' => $component->code,
                        'label' => $component->name,
                        'amount' => $amount,
                        'type' => 'deduction',
                    ];
                }

                // Bảo hiểm phần NLĐ (tỷ lệ demo — xem ghi chú đầu file).
                $insuranceEmployee = $this->calculateInsuranceEmployee($base);
                $insuranceEmployer = $this->calculateInsuranceEmployer($base);
                $lines[] = [
                    'component_code' => 'BHXH',
                    'label' => 'BHXH (8%)',
                    'amount' => round($base * self::BHXH_EMPLOYEE_RATE, 2),
                    'type' => 'deduction',
                ];
                $lines[] = [
                    'component_code' => 'BHYT',
                    'label' => 'BHYT (1,5%)',
                    'amount' => round($base * self::BHYT_EMPLOYEE_RATE, 2),
                    'type' => 'deduction',
                ];
                $lines[] = [
                    'component_code' => 'BHTN',
                    'label' => 'BHTN (1%)',
                    'amount' => round($base * self::BHTN_EMPLOYEE_RATE, 2),
                    'type' => 'deduction',
                ];

                // Thuế TNCN lũy tiến rút gọn (demo — xem ghi chú đầu file).
                $taxableIncome = max(0.0, $gross - $deductionTotal - $insuranceEmployee - self::PERSONAL_DEDUCTION);
                $tax = $this->calculateTax($taxableIncome);
                $lines[] = [
                    'component_code' => 'TNCN',
                    'label' => 'Thuế TNCN',
                    'amount' => $tax,
                    'type' => 'deduction',
                ];

                $net = $gross - $deductionTotal - $insuranceEmployee - $tax;

                $payslip = Payslip::create([
                    'company_id' => $run->company_id,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'base_salary' => $base,
                    'allowances' => $allowanceTotal,
                    'deductions' => $deductionTotal,
                    'insurance_employee' => $insuranceEmployee,
                    'tax' => $tax,
                    'net_salary' => $net,
                    'status' => 'draft',
                ]);

                $payslip->lines()->createMany($lines);

                $totals['gross'] += $gross;
                $totals['allowances'] += $allowanceTotal;
                $totals['deductions'] += $deductionTotal;
                $totals['insurance_employee'] += $insuranceEmployee;
                $totals['insurance_employer'] += $insuranceEmployer;
                $totals['tax'] += $tax;
                $totals['net'] += $net;
            }

            $run->update([
                'status' => PayrollRun::STATUS_CALCULATED,
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_tax' => $totals['tax'],
                'total_insurance' => $totals['insurance_employee'],
                'total_net' => $totals['net'],
            ]);

            return $run->fresh();
        });
    }

    /**
     * Khóa kỳ lương: chỉ khi đã tính xong (calculated). Sau khi khóa,
     * phiếu lương chuyển sang locked và phát event PayrollRunLocked để
     * module Kế toán sinh bút toán lương. Event dispatch SAU KHI COMMIT
     * (DB::afterCommit) theo mục 7 hợp đồng.
     */
    public function lock(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_CALCULATED) {
            throw new DomainException('Chỉ khóa được kỳ lương đã tính xong (trạng thái calculated).');
        }

        $totals = null;

        DB::transaction(function () use ($run, &$totals) {
            $run->update([
                'status' => PayrollRun::STATUS_LOCKED,
                'locked_at' => now(),
            ]);
            $run->payslips()->update(['status' => 'locked']);

            // Bảo hiểm phần NSDLĐ không lưu trên payslip nên tính lại
            // từ tổng lương cơ bản để đưa vào event cho Kế toán.
            $totalBase = (float) $run->payslips()->sum('base_salary');

            $totals = [
                'gross' => (float) $run->total_gross,
                'allowances' => (float) $run->payslips()->sum('allowances'),
                'deductions' => (float) $run->total_deductions,
                'insurance_employee' => (float) $run->total_insurance,
                'insurance_employer' => $this->calculateInsuranceEmployer($totalBase),
                'tax' => (float) $run->total_tax,
                'net' => (float) $run->total_net,
            ];

            DB::afterCommit(function () use ($run, $totals) {
                PayrollRunLocked::dispatch($run->id, $run->company_id, $run->period, $totals);
            });
        });

        return $run->fresh();
    }
}
