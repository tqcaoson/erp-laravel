<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\HR\Events\PayrollRunApproved;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryComponent;
use App\Modules\HR\Services\PayrollService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy luồng khóa → duyệt → trả lương (lỗi 7a, đợt 2, 20/11/2026):
 * - lock() chỉ từ calculated, KHÔNG phát event ghi sổ.
 * - approve() chỉ từ locked, phát event PayrollRunApproved cho Kế toán.
 * - pay() chỉ từ approved, chỉ đánh dấu đã trả.
 * - Nhân viên chỉ xem phiếu lương của kỳ locked/approved/paid.
 * - Employee::assignCode() gán mã NV-0001… (worker C dùng lại).
 *
 * LƯU Ý PHỤ THUỘC WORKER C: 2 quyền mới 'hr.payroll.lock' và
 * 'hr.payroll.pay' do worker C thêm vào RolePermissionSeeder, và role
 * nhan_su phải KHÔNG còn quyền 'hr.payroll.approve' (hiện nhan_su có
 * 'hr.*' nên vẫn duyệt được). Test test_nhan_su_post_approve_bi_403
 * sẽ FAIL cho đến khi worker C sửa xong seeder — coordinator chạy lại
 * test này sau khi gộp code của worker C.
 */
class PayrollApprovalFlowTest extends TestCase
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

    /** Kỳ lương đã tính xong (calculated) với 1 nhân viên lương 22 triệu. */
    protected function createCalculatedRun(): PayrollRun
    {
        $this->createEmployee('NV-FLOW');
        $run = $this->createRun();
        $this->payrollService()->calculate($run);

        return $run->fresh();
    }

    public function test_khoa_duyet_tra_dung_thu_tu_va_event_chi_phat_khi_duyet(): void
    {
        $run = $this->createCalculatedRun();
        $this->assertSame(PayrollRun::STATUS_CALCULATED, $run->status);

        // Event::fake() gọi SAU khi tạo fixture: fake trước sẽ chặn cả
        // model event gán company_id của BaseModel.
        Event::fake();

        $run = $this->payrollService()->lock($run);
        $this->assertSame(PayrollRun::STATUS_LOCKED, $run->status);
        $this->assertNotNull($run->locked_at);
        Event::assertNotDispatched(PayrollRunApproved::class);

        $run = $this->payrollService()->approve($run);
        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->status);
        Event::assertDispatched(PayrollRunApproved::class, function (PayrollRunApproved $event) use ($run) {
            return $event->payrollRunId === $run->id
                && $event->period === '2026-10'
                && $event->totals['gross'] === 22000000.0
                && $event->totals['net'] === 19480500.0;
        });

        $run = $this->payrollService()->pay($run);
        $this->assertSame(PayrollRun::STATUS_PAID, $run->status);
        Event::assertDispatchedTimes(PayrollRunApproved::class, 1);
    }

    public function test_approve_khi_chua_khoa_va_pay_khi_chua_duyet_bi_chan(): void
    {
        $run = $this->createCalculatedRun();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Chỉ duyệt được kỳ lương đã khóa');
        $this->payrollService()->approve($run);
    }

    public function test_pay_khi_chua_duyet_bi_chan(): void
    {
        $run = $this->createCalculatedRun();
        $this->payrollService()->lock($run);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Chỉ đánh dấu đã trả được kỳ lương đã duyệt');
        $this->payrollService()->pay($run->fresh());
    }

    public function test_lock_khi_chua_tinh_va_tinh_lai_khi_da_khoa_bi_chan(): void
    {
        $run = $this->createRun();

        try {
            $this->payrollService()->lock($run);
            $this->fail('Khóa kỳ nháp phải ném DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Chỉ khóa được kỳ lương đã tính xong', $e->getMessage());
        }

        $this->createEmployee('NV-LOCK');
        $this->payrollService()->calculate($run);
        $this->payrollService()->lock($run->fresh());

        try {
            $this->payrollService()->calculate($run->fresh());
            $this->fail('Tính lại kỳ đã khóa phải ném DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('đã khóa, không thể tính lại', $e->getMessage());
        }
    }

    public function test_duyet_sinh_but_toan_luong_can_bang(): void
    {
        $run = $this->createCalculatedRun();
        $this->payrollService()->lock($run->fresh());

        // Không fake event: listener thật chạy (queue sync) và ghi sổ.
        $this->payrollService()->approve($run->fresh());

        $entry = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $run->id)
            ->firstOrFail();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);

        // Lương 22 triệu, không phụ cấp/khấu trừ:
        // Nợ 642 = 22tr + BHXH NSDLĐ 4.730.000 = 26.730.000.
        // Có 334 = thực lĩnh 19.480.500; Có 338 = 2.310.000 + 4.730.000
        // = 7.040.000; Có 3335 = thuế 209.500.
        $lines = $entry->lines()->with('account')->get();
        $byAccount = $lines->mapWithKeys(fn ($line) => [$line->account->code => $line]);
        $this->assertSame(26730000.0, (float) $byAccount['642']->debit);
        $this->assertSame(19480500.0, (float) $byAccount['334']->credit);
        $this->assertSame(7040000.0, (float) $byAccount['338']->credit);
        $this->assertSame(209500.0, (float) $byAccount['3335']->credit);
        $this->assertSame(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit')
        );
    }

    /**
     * Hồi quy bút toán lương khi có phụ cấp + khấu trừ (phát hiện đợt 2):
     * gross đã gồm phụ cấp nên Nợ 642 không được cộng thêm allowances
     * (từng đếm trùng), và khấu trừ phải có dòng Có 3388 tương ứng —
     * nếu không bút toán lệch đúng bằng (phụ cấp + khấu trừ).
     */
    public function test_but_toan_luong_can_bang_khi_co_phu_cap_va_khau_tru(): void
    {
        SalaryComponent::create([
            'company_id' => $this->company->id, 'code' => 'PC-AN-TRUA',
            'name' => 'Phụ cấp ăn trưa', 'type' => SalaryComponent::TYPE_ALLOWANCE,
            'calculation' => 'fixed', 'amount' => 1000000, 'is_active' => true,
        ]);
        SalaryComponent::create([
            'company_id' => $this->company->id, 'code' => 'KT-TAM-UNG',
            'name' => 'Khấu trừ tạm ứng', 'type' => SalaryComponent::TYPE_DEDUCTION,
            'calculation' => 'fixed', 'amount' => 500000, 'is_active' => true,
        ]);

        $run = $this->createCalculatedRun();
        $this->payrollService()->lock($run->fresh());
        $this->payrollService()->approve($run->fresh());

        $entry = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $run->id)
            ->firstOrFail();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);

        // Lương 22tr + phụ cấp 1tr = gross 23tr; khấu trừ 500k;
        // BHXH NLĐ 2.310.000; thu nhập tính thuế 4.690.000 → thuế 234.500;
        // thực lĩnh 19.955.500; BHXH NSDLĐ 4.730.000.
        // Nợ 642 = 23tr + 4.730.000 = 27.730.000 (KHÔNG cộng thêm phụ cấp).
        $lines = $entry->lines()->with('account')->get();
        $byAccount = $lines->mapWithKeys(fn ($line) => [$line->account->code => $line]);
        $this->assertSame(27730000.0, (float) $byAccount['642']->debit);
        $this->assertSame(19955500.0, (float) $byAccount['334']->credit);
        $this->assertSame(7040000.0, (float) $byAccount['338']->credit);
        $this->assertSame(234500.0, (float) $byAccount['3335']->credit);
        $this->assertSame(500000.0, (float) $byAccount['3388']->credit);
        $this->assertSame(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit')
        );
    }

    public function test_admin_duyet_va_tra_qua_web(): void
    {
        $run = $this->createCalculatedRun();

        $this->actingAs($this->admin)
            ->post(route('hr.payroll-runs.lock', $run))
            ->assertRedirect();
        $this->assertSame(PayrollRun::STATUS_LOCKED, $run->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('hr.payroll-runs.approve', $run))
            ->assertRedirect();
        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('hr.payroll-runs.pay', $run))
            ->assertRedirect();
        $this->assertSame(PayrollRun::STATUS_PAID, $run->fresh()->status);
    }

    public function test_api_duyet_va_tra(): void
    {
        $run = $this->createCalculatedRun();
        $this->payrollService()->lock($run->fresh());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson(route('api.hr.payroll-runs.approve', $run))
            ->assertOk()
            ->assertJsonPath('status', PayrollRun::STATUS_APPROVED);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson(route('api.hr.payroll-runs.pay', $run))
            ->assertOk()
            ->assertJsonPath('status', PayrollRun::STATUS_PAID);
    }

    public function test_ke_toan_post_lock_approve_bi_403(): void
    {
        $run = $this->createCalculatedRun();
        $keToan = User::where('email', 'ketoan@erp.local')->firstOrFail();

        $this->actingAs($keToan)->post(route('hr.payroll-runs.lock', $run))->assertForbidden();
        $this->actingAs($keToan)->post(route('hr.payroll-runs.approve', $run))->assertForbidden();
        $this->assertSame(PayrollRun::STATUS_CALCULATED, $run->fresh()->status);
    }

    /**
     * PHỤ THUỘC WORKER C: test này chỉ pass sau khi worker C sửa
     * RolePermissionSeeder (role nhan_su không còn quyền
     * 'hr.payroll.approve'). Hiện nhan_su có 'hr.*' nên vẫn duyệt được.
     */
    public function test_nhan_su_post_approve_bi_403(): void
    {
        $run = $this->createCalculatedRun();
        $this->payrollService()->lock($run->fresh());
        $nhanSu = User::where('email', 'nhansu@erp.local')->firstOrFail();

        $this->actingAs($nhanSu)->post(route('hr.payroll-runs.approve', $run))->assertForbidden();
        $this->assertSame(PayrollRun::STATUS_LOCKED, $run->fresh()->status);
    }

    public function test_nhan_vien_chi_xem_phieu_luong_ky_da_khoa(): void
    {
        $nhanVien = User::where('email', 'nhanvien@erp.local')->firstOrFail();
        $employee = $this->createEmployee('NV-MINH', 22_000_000, ['user_id' => $nhanVien->id]);

        $run = $this->createRun();
        $this->payrollService()->calculate($run);
        $payslip = Payslip::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        // Kỳ mới tính xong (calculated): chưa xem được.
        $this->actingAs($nhanVien)->get(route('my.payslips.show', $payslip))->assertNotFound();
        $this->actingAs($nhanVien)->get('/my/payslips')->assertOk()
            ->assertInertia(fn ($page) => $page->has('payslips.data', 0));

        // Khóa kỳ xong thì xem được.
        $this->payrollService()->lock($run->fresh());
        $this->actingAs($nhanVien)->get(route('my.payslips.show', $payslip))->assertOk();
        $this->actingAs($nhanVien)->get('/my/payslips')->assertOk()
            ->assertInertia(fn ($page) => $page->has('payslips.data', 1));
    }

    public function test_employee_assign_code_gan_ma_nv_theo_id(): void
    {
        $employee = Employee::create([
            'company_id' => $this->company->id,
            'code' => 'TMP-test',
            'full_name' => 'Nhân Viên Mã Mới',
            'department_id' => $this->department->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        Employee::assignCode($employee);

        $this->assertSame('NV-'.str_pad($employee->id, 4, '0', STR_PAD_LEFT), $employee->fresh()->code);
    }

    public function test_tao_nhan_vien_qua_controller_gan_ma_nv_va_luu_truong_moi(): void
    {
        $this->actingAs($this->admin)->post(route('hr.employees.store'), [
            'full_name' => 'Trần Thị Mới',
            'department_id' => $this->department->id,
            'hire_date' => '2026-10-01',
            'termination_date' => '2026-10-15',
            'dependents_count' => 2,
            'base_salary' => 22000000,
        ])->assertRedirect();

        $employee = Employee::where('full_name', 'Trần Thị Mới')->firstOrFail();
        $this->assertMatchesRegularExpression('/^NV-\d{4}$/', $employee->code);
        $this->assertSame(2, $employee->dependents_count);
        $this->assertSame('2026-10-15', $employee->termination_date->format('Y-m-d'));

        // Cập nhật 2 trường mới qua form sửa.
        $this->actingAs($this->admin)->put(route('hr.employees.update', $employee), [
            'full_name' => 'Trần Thị Mới',
            'department_id' => $this->department->id,
            'dependents_count' => 3,
            'termination_date' => null,
        ])->assertRedirect();

        $this->assertSame(3, $employee->fresh()->dependents_count);
        $this->assertNull($employee->fresh()->termination_date);
    }
}
