<?php

namespace Tests\Feature;

use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveBalance;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryComponent;
use App\Modules\HR\Services\LeaveService;
use App\Modules\HR\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy ngày công + lương prorat (đợt 2, 20/11/2026):
 * - Lương prorat theo ngày công hưởng lương; phép năm/nghỉ ốm không trừ.
 * - Đơn nghỉ cắt ngang 2 tháng chỉ trừ phần trong tháng.
 * - Vắng mặt trừ công, không trừ trùng ngày đã nằm trong đơn unpaid.
 * - Vào/ra giữa tháng chỉ hưởng phần tháng làm việc; người nghỉ giữa
 *   tháng vẫn được tính lương.
 * - Duyệt đơn nghỉ sinh log chấm công status=leave.
 *
 * Ngày dùng trong test (đã kiểm tra bằng `date`):
 * - Tháng 10/2026: 22 ngày làm việc. 1/10 = thứ 5, 2/10 = thứ 6,
 *   5/10 = thứ 2, 9/10 = thứ 6, 10/10 = thứ 7, 12/10 = thứ 2,
 *   15/10 = thứ 5, 31/10 = thứ 7.
 * - Tháng 9/2026: 22 ngày làm việc. 28/9 = thứ 2, 30/9 = thứ 4.
 */
class PayrollWorkdaysTest extends TestCase
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

    protected function leaveService(): LeaveService
    {
        return app(LeaveService::class);
    }

    protected function createEmployee(string $code, float $baseSalary = 22_000_000, array $extra = []): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'full_name' => 'Nhân viên '.$code,
            'department_id' => $this->department->id,
            'status' => Employee::STATUS_ACTIVE,
            'base_salary' => $baseSalary,
            ...$extra,
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

    protected function payslipFor(PayrollRun $run, Employee $employee): ?Payslip
    {
        return Payslip::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->first();
    }

    /** Tạo + duyệt đơn nghỉ cho nhân viên (mặc định unpaid). */
    protected function approveLeave(Employee $employee, string $start, string $end, string $type = 'unpaid'): void
    {
        $request = $this->leaveService()->request($employee, [
            'leave_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Đơn nghỉ kiểm thử',
        ]);
        $this->leaveService()->approve($request, $this->admin->id);
    }

    public function test_nghi_unpaid_5_ngay_luong_bang_base_nhan_17_chia_22(): void
    {
        $employee = $this->createEmployee('NV-U5');
        $this->approveLeave($employee, '2026-10-05', '2026-10-09'); // T2–T6: 5 ngày.

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip = $this->payslipFor($run, $employee);
        $this->assertNotNull($slip);
        $this->assertSame(22.0, (float) $slip->standard_working_days);
        $this->assertSame(17.0, (float) $slip->worked_days);
        $this->assertSame(5.0, (float) $slip->unpaid_leave_days);
        // Lương prorat: 22.000.000 / 22 × 17 = 17.000.000.
        $this->assertSame(17000000.0, (float) $slip->base_salary);
        // Dòng chi tiết ngày công trên phiếu lương.
        $this->assertNotNull($slip->lines->firstWhere('component_code', 'WORKDAYS'));
        $this->assertSame(
            'Ngày công 17/22',
            $slip->lines->firstWhere('component_code', 'WORKDAYS')->label
        );
        // BHXH vẫn tính trên lương hợp đồng 22 triệu (không prorat).
        $this->assertSame(2310000.0, (float) $slip->insurance_employee);
    }

    public function test_phep_nam_va_nghi_om_khong_tru_ngay_cong(): void
    {
        $nghiPhep = $this->createEmployee('NV-PHEP');
        $this->approveLeave($nghiPhep, '2026-10-05', '2026-10-09', 'annual');

        $nghiOm = $this->createEmployee('NV-OM');
        $this->approveLeave($nghiOm, '2026-10-05', '2026-10-09', 'sick');

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        foreach ([$nghiPhep, $nghiOm] as $employee) {
            $slip = $this->payslipFor($run, $employee);
            $this->assertNotNull($slip);
            $this->assertSame(22.0, (float) $slip->worked_days);
            $this->assertSame(0.0, (float) $slip->unpaid_leave_days);
            $this->assertSame(22000000.0, (float) $slip->base_salary);
        }
    }

    public function test_don_nghi_cat_ngang_2_thang_chi_tru_phan_trong_thang(): void
    {
        $employee = $this->createEmployee('NV-CAT');
        // Đơn unpaid T2 28/9 → T6 2/10: 3 ngày trong tháng 9, 2 ngày trong tháng 10.
        $this->approveLeave($employee, '2026-09-28', '2026-10-02');

        $runOct = $this->createRun('2026-10');
        $this->payrollService()->calculate($runOct);
        $slipOct = $this->payslipFor($runOct, $employee);
        $this->assertSame(2.0, (float) $slipOct->unpaid_leave_days);
        $this->assertSame(20.0, (float) $slipOct->worked_days);
        $this->assertSame(20000000.0, (float) $slipOct->base_salary);

        $runSep = $this->createRun('2026-09');
        $this->payrollService()->calculate($runSep);
        $slipSep = $this->payslipFor($runSep, $employee);
        $this->assertSame(3.0, (float) $slipSep->unpaid_leave_days);
        $this->assertSame(19.0, (float) $slipSep->worked_days);
        $this->assertSame(19000000.0, (float) $slipSep->base_salary);
    }

    public function test_vang_mat_tru_cong_khong_trung_voi_don_unpaid_di_muon_du_cong(): void
    {
        $employee = $this->createEmployee('NV-VANG');
        $this->approveLeave($employee, '2026-10-05', '2026-10-09'); // 5 ngày unpaid.

        // Vắng T4 7/10 (đã nằm trong đơn unpaid → không đếm lại),
        // vắng T2 12/10 (đếm thêm 1), vắng T7 10/10 (cuối tuần, không đếm).
        foreach (['2026-10-07', '2026-10-12', '2026-10-10'] as $date) {
            AttendanceLog::create([
                'company_id' => $this->company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'status' => AttendanceLog::STATUS_ABSENT,
            ]);
        }
        // Đi muộn T3 13/10: tính đủ công, không trừ.
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-13',
            'status' => AttendanceLog::STATUS_LATE,
        ]);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip = $this->payslipFor($run, $employee);
        $this->assertSame(6.0, (float) $slip->unpaid_leave_days); // 5 unpaid + 1 absent.
        $this->assertSame(16.0, (float) $slip->worked_days);
        $this->assertSame(16000000.0, (float) $slip->base_salary);
    }

    public function test_nhan_vien_vao_giua_thang_chi_huong_phan_thang_lam_viec(): void
    {
        // Vào làm T2 12/10: khoảng làm việc 12/10–31/10 có 15 ngày làm việc.
        $employee = $this->createEmployee('NV-VAO', 22_000_000, ['hire_date' => '2026-10-12']);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip = $this->payslipFor($run, $employee);
        $this->assertNotNull($slip);
        $this->assertSame(22.0, (float) $slip->standard_working_days);
        $this->assertSame(15.0, (float) $slip->worked_days);
        $this->assertSame(15000000.0, (float) $slip->base_salary);
        // BHXH tính trên lương hợp đồng, không prorat theo ngày vào.
        $this->assertSame(2310000.0, (float) $slip->insurance_employee);
    }

    public function test_nhan_vien_nghi_giua_thang_van_duoc_tinh_luong_phan_da_lam(): void
    {
        // Nghỉ việc T5 15/10 (status resigned): khoảng làm việc 1/10–15/10
        // có 11 ngày làm việc → vẫn được tính lương phần đã làm.
        $nghiGiuaThang = $this->createEmployee('NV-NGHI', 22_000_000, [
            'status' => Employee::STATUS_RESIGNED,
            'termination_date' => '2026-10-15',
        ]);

        // Nghỉ việc từ tháng trước (30/9) thì tháng 10 không có lương.
        $nghiThangTruoc = $this->createEmployee('NV-NGHI9', 22_000_000, [
            'status' => Employee::STATUS_RESIGNED,
            'termination_date' => '2026-09-30',
        ]);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip = $this->payslipFor($run, $nghiGiuaThang);
        $this->assertNotNull($slip);
        $this->assertSame(11.0, (float) $slip->worked_days);
        $this->assertSame(11000000.0, (float) $slip->base_salary);

        $this->assertNull($this->payslipFor($run, $nghiThangTruoc));
    }

    public function test_nhan_vien_vao_sau_thang_khong_co_phieu_luong(): void
    {
        $employee = $this->createEmployee('NV-VAOSAU', 22_000_000, ['hire_date' => '2026-11-02']);

        $run = $this->createRun('2026-10');
        $this->payrollService()->calculate($run);

        $this->assertNull($this->payslipFor($run, $employee));
    }

    public function test_phu_cap_prorat_theo_ngay_cong(): void
    {
        // Phụ cấp cố định 2.200.000 + phụ cấp 10% lương.
        SalaryComponent::create([
            'company_id' => $this->company->id,
            'code' => 'PC-CO-DINH', 'name' => 'Phụ cấp cố định',
            'type' => SalaryComponent::TYPE_ALLOWANCE,
            'calculation' => 'fixed', 'amount' => 2_200_000, 'is_active' => true,
        ]);
        SalaryComponent::create([
            'company_id' => $this->company->id,
            'code' => 'PC-PHAN-TRAM', 'name' => 'Phụ cấp %',
            'type' => SalaryComponent::TYPE_ALLOWANCE,
            'calculation' => 'percent', 'amount' => 10, 'is_active' => true,
        ]);

        $employee = $this->createEmployee('NV-PC');
        $this->approveLeave($employee, '2026-10-05', '2026-10-09'); // Nghỉ 5/22 ngày.

        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        $slip = $this->payslipFor($run, $employee);
        // Lương prorat 17 triệu; phụ cấp cố định 2,2tr × 17/22 = 1,7tr;
        // phụ cấp % = 10% × 17tr = 1,7tr; tổng phụ cấp 3,4tr.
        $this->assertSame(17000000.0, (float) $slip->base_salary);
        $this->assertSame(3400000.0, (float) $slip->allowances);
        $this->assertSame(20400000.0, $slip->gross());
    }

    public function test_duyet_don_nghi_sinh_log_cham_cong_leave(): void
    {
        $employee = $this->createEmployee('NV-LOG');

        // Đơn phép năm T6 2/10 → T2 5/10: chỉ 2 ngày làm việc (T6, T2).
        $request = $this->leaveService()->request($employee, [
            'leave_type' => 'annual',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-05',
            'reason' => 'Nghỉ cuối tuần dài',
        ]);

        // Chấm công đã ghi vắng T2 5/10 trước khi đơn được duyệt.
        AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-05',
            'status' => AttendanceLog::STATUS_ABSENT,
        ]);

        $this->leaveService()->approve($request, $this->admin->id);

        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->orderBy('work_date')
            ->get();
        $this->assertCount(2, $logs);
        $this->assertSame('2026-10-02', $logs[0]->work_date->format('Y-m-d'));
        $this->assertSame('2026-10-05', $logs[1]->work_date->format('Y-m-d'));
        // Log absent cũ bị ghi đè thành leave; không có log T7/CN.
        $this->assertTrue($logs->every(fn ($log) => $log->status === AttendanceLog::STATUS_LEAVE));
        $this->assertSame(2.0, (float) LeaveBalance::where('employee_id', $employee->id)->first()->used_days);
    }
}
