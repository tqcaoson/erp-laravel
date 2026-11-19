<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Events\PayrollRunApproved;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryComponent;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ tính lương.
 *
 * CÔNG THỨC:
 *   Ngày công chuẩn = số ngày T2–T6 trong tháng.
 *   Ngày công hưởng lương = ngày làm việc trong khoảng làm việc của
 *   nhân viên trong tháng (từ ngày vào làm đến ngày nghỉ việc, cắt
 *   theo đầu/cuối tháng) trừ ngày nghỉ không lương và ngày vắng mặt.
 *   Lương cơ bản prorat = lương hợp đồng / ngày công chuẩn × ngày công
 *   hưởng lương. Phụ cấp % tính trên lương prorat, phụ cấp cố định
 *   nhân theo tỷ lệ ngày công.
 *   gross = lương prorat + phụ cấp
 *   net   = gross − khấu trừ − bảo hiểm phần NLĐ − thuế TNCN
 *
 * CĂN CỨ LUẬT (cập nhật đợt 2, 20/11/2026 — kiểm tra lại với kế toán
 * khi luật thay đổi):
 *  - Thuế TNCN: biểu lũy tiến 5 bậc theo tháng, Luật Thuế TNCN
 *    109/2025/QH15 Điều 9; giảm trừ bản thân 15.500.000 đ/tháng và
 *    người phụ thuộc 6.200.000 đ/người/tháng (Điều 10).
 *  - Bảo hiểm: NLĐ BHXH 8%, BHYT 1,5%, BHTN 1%; NSDLĐ BHXH 17,5%
 *    (gồm 0,5% TNLĐ-BNN), BHYT 3%, BHTN 1%. Trần đóng BHXH/BHYT
 *    50.600.000 đ/tháng (20 × lương cơ sở 2.530.000 từ 1/7/2026,
 *    NĐ 161/2026); trần BHTN 106.200.000 đ/tháng (20 × lương tối
 *    thiểu vùng I 5.310.000, NĐ 293/2025).
 *  - Tháng nghỉ không lương từ 14 ngày làm việc trở lên thì tháng đó
 *    không đóng BHXH/BHYT/BHTN (Luật BHXH — NLĐ không làm việc và
 *    không hưởng lương từ 14 ngày làm việc trở lên trong tháng thì
 *    không đóng BHXH tháng đó).
 *  - Nghỉ ốm tính như ngày công hưởng lương; đi muộn tính đủ công.
 *  - BHXH tính trên lương hợp đồng, KHÔNG prorat theo ngày công.
 */
class PayrollService
{
    public function __construct(
        protected LeaveService $leaveService,
    ) {}

    // Tỷ lệ bảo hiểm phần người lao động.
    public const BHXH_EMPLOYEE_RATE = 0.08;   // 8%
    public const BHYT_EMPLOYEE_RATE = 0.015;  // 1,5%
    public const BHTN_EMPLOYEE_RATE = 0.01;   // 1%

    // Tỷ lệ bảo hiểm phần người sử dụng lao động (chỉ dùng để báo
    // cáo tổng chi phí trong event PayrollRunApproved, không trừ vào lương NLĐ).
    public const BHXH_EMPLOYER_RATE = 0.175;  // 17,5% (gồm 0,5% TNLĐ-BNN)
    public const BHYT_EMPLOYER_RATE = 0.03;   // 3%
    public const BHTN_EMPLOYER_RATE = 0.01;   // 1%

    /**
     * Trần lương đóng BHXH/BHYT: 50.600.000 đ/tháng
     * (20 × lương cơ sở 2.530.000 từ 1/7/2026, NĐ 161/2026).
     */
    public const SI_CAP = 50_600_000;

    /**
     * Trần lương đóng BHTN: 106.200.000 đ/tháng
     * (20 × lương tối thiểu vùng I 5.310.000, NĐ 293/2025).
     * Hằng số này theo VÙNG I — công ty ở vùng khác thì đổi hằng số
     * này theo lương tối thiểu vùng đó.
     */
    public const UI_CAP = 106_200_000;

    /**
     * Số ngày nghỉ không lương tối thiểu trong tháng để được miễn
     * đóng BHXH/BHYT/BHTN tháng đó (Luật BHXH).
     */
    public const SI_EXEMPT_UNPAID_DAYS = 14;

    /** Giảm trừ bản thân khi tính thuế TNCN (đồng/tháng, Luật 109/2025/QH15 Điều 10). */
    public const PERSONAL_DEDUCTION = 15_500_000;

    /** Giảm trừ cho mỗi người phụ thuộc (đồng/người/tháng, Luật 109/2025/QH15 Điều 10). */
    public const DEPENDENT_DEDUCTION = 6_200_000;

    /**
     * Biểu thuế TNCN lũy tiến 5 bậc theo tháng (Luật 109/2025/QH15 Điều 9).
     * Mỗi bậc: [giới hạn trên của thu nhập tính thuế, thuế suất].
     * Thu nhập tính thuế = gross − khấu trừ − bảo hiểm NLĐ
     *   − giảm trừ bản thân − giảm trừ người phụ thuộc.
     */
    public const TAX_BRACKETS = [
        [10_000_000, 0.05],  // đến 10 triệu: 5%
        [30_000_000, 0.10],  // trên 10–30 triệu: 10%
        [60_000_000, 0.20],  // trên 30–60 triệu: 20%
        [100_000_000, 0.30], // trên 60–100 triệu: 30%
        [PHP_INT_MAX, 0.35], // trên 100 triệu: 35%
    ];

    /**
     * Tính thuế TNCN lũy tiến từng phần.
     * Ví dụ thu nhập tính thuế 15 triệu: 10tr × 5% + 5tr × 10% = 1.000.000.
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

    /**
     * Chi tiết bảo hiểm phần NLĐ trên lương hợp đồng (KHÔNG prorat
     * theo ngày công). BHXH/BHYT chung trần 50,6 triệu, BHTN trần
     * riêng 106,2 triệu. Nghỉ không lương từ 14 ngày làm việc trở
     * lên trong tháng thì miễn đóng cả 3 loại.
     *
     * @return array{bhxh: float, bhyt: float, bhtn: float, total: float}
     */
    public function insuranceBreakdownEmployee(float $baseSalary, float $unpaidDays = 0): array
    {
        if ($unpaidDays >= self::SI_EXEMPT_UNPAID_DAYS) {
            return ['bhxh' => 0.0, 'bhyt' => 0.0, 'bhtn' => 0.0, 'total' => 0.0];
        }

        $siBase = min($baseSalary, self::SI_CAP);
        $uiBase = min($baseSalary, self::UI_CAP);

        $bhxh = round($siBase * self::BHXH_EMPLOYEE_RATE, 2);
        $bhyt = round($siBase * self::BHYT_EMPLOYEE_RATE, 2);
        $bhtn = round($uiBase * self::BHTN_EMPLOYEE_RATE, 2);

        return ['bhxh' => $bhxh, 'bhyt' => $bhyt, 'bhtn' => $bhtn, 'total' => round($bhxh + $bhyt + $bhtn, 2)];
    }

    /** Bảo hiểm phần NLĐ trên lương hợp đồng (áp trần, miễn khi nghỉ không lương ≥ 14 ngày). */
    public function calculateInsuranceEmployee(float $baseSalary, float $unpaidDays = 0): float
    {
        return $this->insuranceBreakdownEmployee($baseSalary, $unpaidDays)['total'];
    }

    /**
     * Chi tiết bảo hiểm phần NSDLĐ (chỉ để báo cáo tổng chi phí).
     * Cùng trần đóng và cùng quy tắc miễn như phần NLĐ.
     *
     * @return array{bhxh: float, bhyt: float, bhtn: float, total: float}
     */
    public function insuranceBreakdownEmployer(float $baseSalary, float $unpaidDays = 0): array
    {
        if ($unpaidDays >= self::SI_EXEMPT_UNPAID_DAYS) {
            return ['bhxh' => 0.0, 'bhyt' => 0.0, 'bhtn' => 0.0, 'total' => 0.0];
        }

        $siBase = min($baseSalary, self::SI_CAP);
        $uiBase = min($baseSalary, self::UI_CAP);

        $bhxh = round($siBase * self::BHXH_EMPLOYER_RATE, 2);
        $bhyt = round($siBase * self::BHYT_EMPLOYER_RATE, 2);
        $bhtn = round($uiBase * self::BHTN_EMPLOYER_RATE, 2);

        return ['bhxh' => $bhxh, 'bhyt' => $bhyt, 'bhtn' => $bhtn, 'total' => round($bhxh + $bhyt + $bhtn, 2)];
    }

    /** Bảo hiểm phần người sử dụng lao động (chỉ để báo cáo tổng chi phí). */
    public function calculateInsuranceEmployer(float $baseSalary, float $unpaidDays = 0): float
    {
        return $this->insuranceBreakdownEmployer($baseSalary, $unpaidDays)['total'];
    }

    /**
     * Số ngày làm việc (T2–T6) trong khoảng [start, end], tính cả 2 đầu.
     * Khác LeaveService::countDays(): khoảng rỗng hoặc không có ngày
     * làm việc nào thì trả 0 thay vì ném exception — dùng cho phần
     * giao nhau của đơn nghỉ / khoảng làm việc với tháng lương, vốn
     * có thể rỗng một cách hợp lệ.
     */
    protected function countWeekdays(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

        $days = 0;
        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            if (! $day->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Tập hợp các ngày nghỉ không lương (dạng 'Y-m-d') của nhân viên
     * trong khoảng làm việc, giao với tháng lương: chỉ tính đơn nghỉ
     * unpaid ĐÃ DUYỆT, chỉ đếm ngày làm việc (T2–T6). Đơn cắt ngang
     * 2 tháng chỉ đóng góp phần nằm trong tháng này.
     *
     * @return array<string, true> tập hợp ngày 'Y-m-d'
     */
    protected function unpaidLeaveDates(Employee $employee, Carbon $monthStart, Carbon $monthEnd, Carbon $windowStart, Carbon $windowEnd): array
    {
        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type', LeaveRequest::TYPE_UNPAID)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->get(['start_date', 'end_date']);

        $dates = [];

        foreach ($requests as $request) {
            $start = $request->start_date->copy()->max($monthStart)->max($windowStart);
            $end = $request->end_date->copy()->min($monthEnd)->min($windowEnd);

            for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                if (! $day->isWeekend()) {
                    $dates[$day->format('Y-m-d')] = true;
                }
            }
        }

        return $dates;
    }

    /**
     * Ngày công của 1 nhân viên trong tháng lương.
     *
     * - standardDays: số ngày T2–T6 của cả tháng (dùng chung cho mọi
     *   nhân viên, tái dùng LeaveService::countDays).
     * - unpaidDays: ngày nghỉ không lương đã duyệt + ngày vắng mặt
     *   (AttendanceLog status=absent) trong khoảng làm việc, trừ
     *   trùng theo ngày (ngày đã nằm trong đơn unpaid thì không đếm
     *   lại ở absent). Phép năm và nghỉ ốm KHÔNG trừ công; đi muộn
     *   tính đủ công.
     * - workedDays: ngày làm việc trong khoảng làm việc trừ unpaidDays.
     *   Khoảng làm việc = [max(đầu tháng, hire_date), min(cuối tháng,
     *   termination_date)] — nhân viên vào/ra giữa tháng chỉ hưởng
     *   phần tháng đã làm việc.
     *
     * @return array{standard: float, worked: float, unpaid: float}
     */
    public function workdaysFor(Employee $employee, Carbon $monthStart, Carbon $monthEnd): array
    {
        $standardDays = $this->leaveService->countDays(
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d'),
        );

        $windowStart = $monthStart->copy();
        if ($employee->hire_date) {
            $windowStart = $windowStart->max($employee->hire_date->copy()->startOfDay());
        }

        $windowEnd = $monthEnd->copy();
        if ($employee->termination_date) {
            $windowEnd = $windowEnd->min($employee->termination_date->copy()->startOfDay());
        }

        $windowWeekdays = $this->countWeekdays($windowStart, $windowEnd);

        if ($windowWeekdays === 0) {
            return ['standard' => $standardDays, 'worked' => 0.0, 'unpaid' => 0.0];
        }

        $unpaidDates = $this->unpaidLeaveDates($employee, $monthStart, $monthEnd, $windowStart, $windowEnd);

        // Ngày vắng mặt trong khoảng làm việc, trừ ngày đã nằm trong
        // đơn nghỉ không lương (tránh trừ trùng 1 ngày 2 lần).
        $absentDates = AttendanceLog::where('employee_id', $employee->id)
            ->where('status', AttendanceLog::STATUS_ABSENT)
            ->whereDate('work_date', '>=', $windowStart)
            ->whereDate('work_date', '<=', $windowEnd)
            ->pluck('work_date');

        foreach ($absentDates as $workDate) {
            $day = Carbon::parse($workDate);
            if (! $day->isWeekend()) {
                $unpaidDates[$day->format('Y-m-d')] = true;
            }
        }

        $unpaidDays = (float) count($unpaidDates);

        return [
            'standard' => $standardDays,
            'worked' => (float) ($windowWeekdays - $unpaidDays),
            'unpaid' => $unpaidDays,
        ];
    }

    /**
     * Tính lương cho 1 kỳ lương.
     *
     * - Tính cho nhân viên đang hoạt động (status active) HOẶC có ngày
     *   nghỉ việc trong tháng (người nghỉ giữa tháng vẫn được tính
     *   lương phần đã làm).
     * - Nhân viên có khoảng làm việc rỗng trong tháng (vào sau cuối
     *   tháng, nghỉ trước đầu tháng) thì bỏ qua, không tạo phiếu lương.
     * - Chạy trong transaction; idempotent: kỳ ở trạng thái draft hoặc
     *   calculated sẽ xóa phiếu lương cũ rồi tính lại từ đầu.
     * - Kỳ đã khóa/duyệt/trả thì không được tính lại.
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

            $monthStart = Carbon::parse($run->period.'-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $components = SalaryComponent::where('is_active', true)->get();
            $allowances = $components->where('type', SalaryComponent::TYPE_ALLOWANCE);
            $deductions = $components->where('type', SalaryComponent::TYPE_DEDUCTION);

            // Nhân viên đang hoạt động, cộng nhân viên nghỉ việc trong
            // tháng này (dù status đã là resigned).
            $employees = Employee::where(function ($query) use ($monthStart, $monthEnd) {
                $query->where('status', Employee::STATUS_ACTIVE)
                    ->orWhereBetween('termination_date', [
                        $monthStart->format('Y-m-d'),
                        $monthEnd->format('Y-m-d'),
                    ]);
            })
                ->orderBy('code')
                ->get();

            $totals = [
                'gross' => 0.0, 'allowances' => 0.0, 'deductions' => 0.0,
                'insurance_employee' => 0.0, 'insurance_employer' => 0.0,
                'tax' => 0.0, 'net' => 0.0,
            ];

            foreach ($employees as $employee) {
                $contractBase = (float) $employee->base_salary;

                $workdays = $this->workdaysFor($employee, $monthStart, $monthEnd);

                // Khoảng làm việc rỗng trong tháng → không tạo phiếu lương.
                if ($workdays['worked'] <= 0 && $workdays['unpaid'] <= 0) {
                    continue;
                }

                $standardDays = $workdays['standard'];
                $paidDays = $workdays['worked'];
                $unpaidDays = $workdays['unpaid'];

                // Lương cơ bản prorat theo ngày công hưởng lương.
                $proratedBase = $standardDays > 0
                    ? round($contractBase / $standardDays * $paidDays, 2)
                    : 0.0;

                // Phụ cấp: % tính trên lương prorat; cố định nhân theo
                // tỷ lệ ngày công (paidDays/standardDays).
                $allowanceTotal = 0.0;
                $lines = [];
                foreach ($allowances as $component) {
                    $amount = $component->calculation === 'percent'
                        ? $component->amountFor($proratedBase)
                        : round($component->amountFor($contractBase) * ($standardDays > 0 ? $paidDays / $standardDays : 0), 2);
                    $allowanceTotal += $amount;
                    $lines[] = [
                        'component_code' => $component->code,
                        'label' => $component->name,
                        'amount' => $amount,
                        'type' => 'earning',
                    ];
                }

                $gross = $proratedBase + $allowanceTotal;

                // Khấu trừ: giữ nguyên cách tính trên lương hợp đồng.
                $deductionTotal = 0.0;
                foreach ($deductions as $component) {
                    $amount = $component->amountFor($contractBase);
                    $deductionTotal += $amount;
                    $lines[] = [
                        'component_code' => $component->code,
                        'label' => $component->name,
                        'amount' => $amount,
                        'type' => 'deduction',
                    ];
                }

                // Bảo hiểm phần NLĐ trên lương HỢP ĐỒNG (không prorat),
                // áp trần đóng; miễn khi nghỉ không lương ≥ 14 ngày.
                $insurance = $this->insuranceBreakdownEmployee($contractBase, $unpaidDays);
                $insuranceEmployee = $insurance['total'];
                $insuranceEmployer = $this->calculateInsuranceEmployer($contractBase, $unpaidDays);
                $exemptLabel = $unpaidDays >= self::SI_EXEMPT_UNPAID_DAYS ? ' — miễn đóng' : '';
                $lines[] = [
                    'component_code' => 'BHXH',
                    'label' => 'BHXH (8%)'.$exemptLabel,
                    'amount' => $insurance['bhxh'],
                    'type' => 'deduction',
                ];
                $lines[] = [
                    'component_code' => 'BHYT',
                    'label' => 'BHYT (1,5%)'.$exemptLabel,
                    'amount' => $insurance['bhyt'],
                    'type' => 'deduction',
                ];
                $lines[] = [
                    'component_code' => 'BHTN',
                    'label' => 'BHTN (1%)'.$exemptLabel,
                    'amount' => $insurance['bhtn'],
                    'type' => 'deduction',
                ];

                // Thuế TNCN lũy tiến 5 bậc (Luật 109/2025/QH15): giảm trừ
                // bản thân 15,5 triệu + 6,2 triệu mỗi người phụ thuộc.
                $dependents = (int) ($employee->dependents_count ?? 0);
                $taxableIncome = max(0.0, $gross - $deductionTotal - $insuranceEmployee
                    - self::PERSONAL_DEDUCTION - $dependents * self::DEPENDENT_DEDUCTION);
                $tax = $this->calculateTax($taxableIncome);
                $lines[] = [
                    'component_code' => 'TNCN',
                    'label' => 'Thuế TNCN',
                    'amount' => $tax,
                    'type' => 'deduction',
                ];

                // Dòng chi tiết ngày công (số tiền 0, chỉ để hiển thị).
                $lines[] = [
                    'component_code' => 'WORKDAYS',
                    'label' => 'Ngày công '.rtrim(rtrim(number_format($paidDays, 2, '.', ''), '0'), '.')
                        .'/'.rtrim(rtrim(number_format($standardDays, 2, '.', ''), '0'), '.'),
                    'amount' => 0,
                    'type' => 'earning',
                ];

                $net = $gross - $deductionTotal - $insuranceEmployee - $tax;

                $payslip = Payslip::create([
                    'company_id' => $run->company_id,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'base_salary' => $proratedBase,
                    'allowances' => $allowanceTotal,
                    'deductions' => $deductionTotal,
                    'insurance_employee' => $insuranceEmployee,
                    'tax' => $tax,
                    'net_salary' => $net,
                    'status' => 'draft',
                    'standard_working_days' => $standardDays,
                    'worked_days' => $paidDays,
                    'unpaid_leave_days' => $unpaidDays,
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
     * phiếu lương chuyển sang locked và nhân viên xem được phiếu của
     * mình. Khóa KHÔNG phát event ghi sổ — bút toán lương chỉ được
     * sinh khi kỳ được DUYỆT (approve()).
     */
    public function lock(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_CALCULATED) {
            throw new DomainException('Chỉ khóa được kỳ lương đã tính xong (trạng thái calculated).');
        }

        DB::transaction(function () use ($run) {
            $run->update([
                'status' => PayrollRun::STATUS_LOCKED,
                'locked_at' => now(),
            ]);
            $run->payslips()->update(['status' => 'locked']);
        });

        return $run->fresh();
    }

    /**
     * Tổng tiền của kỳ lương để đưa vào event duyệt (cho Kế toán ghi sổ).
     * Bảo hiểm phần NSDLĐ tính lại theo từng nhân viên (lương hợp đồng
     * + trần đóng + miễn khi nghỉ không lương ≥ 14 ngày) vì không lưu
     * trên phiếu lương — không cộng dồn trên tổng lương rồi áp trần 1 lần.
     *
     * @return array{gross: float, allowances: float, deductions: float, insurance_employee: float, insurance_employer: float, tax: float, net: float}
     */
    protected function approvalTotals(PayrollRun $run): array
    {
        $insuranceEmployer = 0.0;

        foreach ($run->payslips()->with('employee')->get() as $payslip) {
            $contractBase = $payslip->employee ? (float) $payslip->employee->base_salary : 0.0;
            $insuranceEmployer += $this->calculateInsuranceEmployer(
                $contractBase,
                (float) $payslip->unpaid_leave_days,
            );
        }

        return [
            'gross' => (float) $run->total_gross,
            'allowances' => (float) $run->payslips()->sum('allowances'),
            'deductions' => (float) $run->total_deductions,
            'insurance_employee' => (float) $run->total_insurance,
            'insurance_employer' => round($insuranceEmployer, 2),
            'tax' => (float) $run->total_tax,
            'net' => (float) $run->total_net,
        ];
    }

    /**
     * Duyệt kỳ lương: chỉ khi đã khóa (locked). Sau khi duyệt, phát
     * event PayrollRunApproved để module Kế toán sinh bút toán lương.
     * Event dispatch SAU KHI COMMIT (DB::afterCommit) theo mục 7 hợp đồng.
     */
    public function approve(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_LOCKED) {
            throw new DomainException('Chỉ duyệt được kỳ lương đã khóa (trạng thái locked).');
        }

        $totals = null;

        DB::transaction(function () use ($run, &$totals) {
            $run->update(['status' => PayrollRun::STATUS_APPROVED]);

            $totals = $this->approvalTotals($run->fresh());

            DB::afterCommit(function () use ($run, $totals) {
                PayrollRunApproved::dispatch($run->id, $run->company_id, $run->period, $totals);
            });
        });

        return $run->fresh();
    }

    /**
     * Đánh dấu đã trả lương: chỉ khi đã duyệt (approved). Chỉ đổi
     * trạng thái, không phát event hay sinh thêm bút toán.
     */
    public function pay(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw new DomainException('Chỉ đánh dấu đã trả được kỳ lương đã duyệt (trạng thái approved).');
        }

        $run->update(['status' => PayrollRun::STATUS_PAID]);

        return $run->fresh();
    }
}
