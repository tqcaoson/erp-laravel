<?php

namespace Tests\Feature;

use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Services\LeaveService;
use App\Modules\HR\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy thuế TNCN + bảo hiểm theo luật mới (đợt 2, 20/11/2026):
 * - Biểu thuế lũy tiến 5 bậc theo tháng (Luật 109/2025/QH15 Điều 9).
 * - Giảm trừ bản thân 15,5 triệu, người phụ thuộc 6,2 triệu/người (Điều 10).
 * - Trần đóng BHXH/BHYT 50,6 triệu, BHTN 106,2 triệu (vùng I).
 * - Miễn đóng BHXH/BHYT/BHTN khi nghỉ không lương ≥ 14 ngày làm việc.
 *
 * Kỳ lương dùng trong test: 2026-10 (22 ngày làm việc, T2–T6).
 */
class PayrollTax2026Test extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseData();
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'PB-TEST',
            'name' => 'Phòng kiểm thử',
        ]);
    }

    protected function payrollService(): PayrollService
    {
        return app(PayrollService::class);
    }

    protected function createEmployee(string $code, float $baseSalary, int $dependents = 0): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'full_name' => 'Nhân viên '.$code,
            'department_id' => $this->department->id,
            'status' => Employee::STATUS_ACTIVE,
            'base_salary' => $baseSalary,
            'dependents_count' => $dependents,
        ]);
    }

    protected function createRun(string $period = '2026-10'): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $this->company->id,
            'code' => 'BL-'.$period,
            'period' => $period,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);
    }

    protected function payslipFor(PayrollRun $run, Employee $employee): Payslip
    {
        return Payslip::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }

    public function test_bieu_thue_5_bac_moi_dung_tung_moc(): void
    {
        $service = $this->payrollService();

        // Dưới 10 triệu: 8tr × 5% = 400.000.
        $this->assertSame(400000.0, $service->calculateTax(8_000_000));
        // Đúng ranh giới bậc 1: 10tr × 5% = 500.000.
        $this->assertSame(500000.0, $service->calculateTax(10_000_000));
        // 15 triệu: 10tr × 5% + 5tr × 10% = 1.000.000.
        $this->assertSame(1000000.0, $service->calculateTax(15_000_000));
        // 40 triệu: 500k + 20tr × 10% + 10tr × 20% = 4.500.000.
        $this->assertSame(4500000.0, $service->calculateTax(40_000_000));
        // 80 triệu: 500k + 2tr + 30tr × 20% + 20tr × 30% = 14.500.000.
        $this->assertSame(14500000.0, $service->calculateTax(80_000_000));
        // 120 triệu: 500k + 2tr + 6tr + 40tr × 30% + 20tr × 35% = 27.500.000.
        $this->assertSame(27500000.0, $service->calculateTax(120_000_000));
        // Không có thu nhập tính thuế thì không đóng thuế.
        $this->assertSame(0.0, $service->calculateTax(0));
        $this->assertSame(0.0, $service->calculateTax(-1000000));
    }

    public function test_giam_tru_nguoi_phu_thuoc_2_nguoi_tru_them_12_4_trieu(): void
    {
        // Lương 50 triệu, không phụ cấp/khấu trừ: bảo hiểm NLĐ
        // = 50tr × (8% + 1,5% + 1%) = 5.250.000 (dưới trần).
        // Không người phụ thuộc: thu nhập tính thuế
        // = 50tr − 5,25tr − 15,5tr = 29,25tr → thuế 2.425.000.
        // 2 người phụ thuộc: thu nhập tính thuế = 29,25tr − 12,4tr
        // = 16,85tr → thuế 1.185.000.
        $khongPhuThuoc = $this->createEmployee('NV-KPT', 50_000_000, 0);
        $haiPhuThuoc = $this->createEmployee('NV-2PT', 50_000_000, 2);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip0 = $this->payslipFor($run, $khongPhuThuoc);
        $slip2 = $this->payslipFor($run, $haiPhuThuoc);

        $this->assertSame(5250000.0, (float) $slip0->insurance_employee);
        $this->assertSame(5250000.0, (float) $slip2->insurance_employee);
        $this->assertSame(2425000.0, (float) $slip0->tax);
        $this->assertSame(1185000.0, (float) $slip2->tax);
        // Chênh lệch thuế đúng 12,4 triệu × 10% (cả 2 mốc đều ở bậc 10%).
        $this->assertSame(1240000.0, (float) $slip0->tax - (float) $slip2->tax);
    }

    public function test_tran_dong_bhxh_bhyt_50_6_trieu_va_bhtn_106_2_trieu(): void
    {
        $service = $this->payrollService();

        // Lương 60 triệu: BHXH/BHYT tính trên 50,6 triệu
        // (4.048.000 + 759.000), BHTN tính trên 60 triệu (600.000).
        $this->assertSame(5407000.0, $service->calculateInsuranceEmployee(60_000_000));
        $breakdown = $service->insuranceBreakdownEmployee(60_000_000);
        $this->assertSame(4048000.0, $breakdown['bhxh']);
        $this->assertSame(759000.0, $breakdown['bhyt']);
        $this->assertSame(600000.0, $breakdown['bhtn']);

        // Phần NSDLĐ lương 60 triệu: 50,6tr × 17,5% + 50,6tr × 3%
        // + 60tr × 1% = 8.855.000 + 1.518.000 + 600.000.
        $this->assertSame(10973000.0, $service->calculateInsuranceEmployer(60_000_000));

        // Lương 120 triệu: BHTN cũng chạm trần 106,2 triệu → 1.062.000.
        $this->assertSame(5869000.0, $service->calculateInsuranceEmployee(120_000_000));
        $this->assertSame(1062000.0, $service->insuranceBreakdownEmployee(120_000_000)['bhtn']);

        // Lương dưới trần thì tính đủ trên lương thực tế.
        $this->assertSame(2100000.0, $service->calculateInsuranceEmployee(20_000_000));
    }

    public function test_mien_bhxh_khi_nghi_khong_luong_tu_14_ngay_lam_viec(): void
    {
        $leaveService = app(LeaveService::class);

        // Nghỉ không lương 14 ngày làm việc: T5 1/10 → T3 20/10
        // (1, 2, 5–9, 12–16, 19, 20) → miễn đóng cả 3 loại bảo hiểm.
        $nghi14 = $this->createEmployee('NV-N14', 22_000_000);
        $don14 = $leaveService->request($nghi14, [
            'leave_type' => 'unpaid',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-20',
            'reason' => 'Nghỉ không lương 14 ngày',
        ]);
        $leaveService->approve($don14, $this->admin->id);

        // Nghỉ không lương 13 ngày làm việc: T5 1/10 → T2 19/10
        // → vẫn đóng bảo hiểm bình thường trên lương hợp đồng.
        $nghi13 = $this->createEmployee('NV-N13', 22_000_000);
        $don13 = $leaveService->request($nghi13, [
            'leave_type' => 'unpaid',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-19',
            'reason' => 'Nghỉ không lương 13 ngày',
        ]);
        $leaveService->approve($don13, $this->admin->id);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip14 = $this->payslipFor($run, $nghi14);
        $this->assertSame(14.0, (float) $slip14->unpaid_leave_days);
        $this->assertSame(0.0, (float) $slip14->insurance_employee);
        $this->assertSame(0.0, $this->payrollService()->calculateInsuranceEmployer(22_000_000, 14));
        $bhxhLine = $slip14->lines->firstWhere('component_code', 'BHXH');
        $this->assertNotNull($bhxhLine);
        $this->assertSame(0.0, (float) $bhxhLine->amount);
        $this->assertStringContainsString('miễn đóng', $bhxhLine->label);

        $slip13 = $this->payslipFor($run, $nghi13);
        $this->assertSame(13.0, (float) $slip13->unpaid_leave_days);
        // BHXH tính trên lương HỢP ĐỒNG 22 triệu, không prorat:
        // 22tr × (8% + 1,5% + 1%) = 2.310.000.
        $this->assertSame(2310000.0, (float) $slip13->insurance_employee);
    }
}
