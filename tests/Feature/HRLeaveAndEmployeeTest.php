<?php

namespace Tests\Feature;

use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveBalance;
use App\Modules\HR\Services\LeaveService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy cho lỗi nghỉ phép + quản lý nhân viên (19/11/2026):
 * - remainingLeaveDays() báo 0 khi chưa có bản ghi số dư (phải là 12).
 * - countDays() đếm cả T7/CN (phải chỉ đếm T2–T6).
 * - Nhân viên thiếu nút Xóa/vô hiệu/khôi phục, thiếu chọn tài khoản User.
 *
 * Ngày dùng trong test (đã kiểm tra bằng `date`):
 * - 2026-11-20 = thứ 6, 2026-11-21 = thứ 7, 2026-11-22 = chủ nhật,
 *   2026-11-23 = thứ 2, 2026-11-27 = thứ 6.
 */
class HRLeaveAndEmployeeTest extends TestCase
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

    protected function createEmployee(string $code = 'NV-TEST', string $name = 'Nguyễn Văn Test'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'full_name' => $name,
            'department_id' => $this->department->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    protected function leaveService(): LeaveService
    {
        return app(LeaveService::class);
    }

    public function test_remaining_leave_days_tra_12_khi_chua_co_ban_ghi_so_du(): void
    {
        $employee = $this->createEmployee();

        $this->assertSame(0, LeaveBalance::count());
        $this->assertSame(12.0, $employee->remainingLeaveDays());
    }

    public function test_dem_ngay_thu_6_den_thu_2_bang_2_ngay_lam_viec(): void
    {
        // Thứ 6 20/11 → thứ 2 23/11: chỉ T6 + T2 là ngày làm việc.
        $this->assertSame(2.0, $this->leaveService()->countDays('2026-11-20', '2026-11-23'));
        // Cả tuần T2 23/11 → T6 27/11 = 5 ngày làm việc.
        $this->assertSame(5.0, $this->leaveService()->countDays('2026-11-23', '2026-11-27'));
    }

    public function test_khong_tao_duoc_don_nghi_khi_thuc_su_het_phep(): void
    {
        $employee = $this->createEmployee();
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'year' => 2026,
            'total_days' => 12,
            'used_days' => 12,
        ]);

        $this->assertSame(0.0, $employee->remainingLeaveDays(2026));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Không đủ ngày phép năm');
        $this->leaveService()->request($employee, [
            'leave_type' => 'annual',
            'start_date' => '2026-11-23', // Thứ 2: 1 ngày làm việc.
            'end_date' => '2026-11-23',
            'reason' => 'Nghỉ khi đã hết phép',
        ]);
    }

    public function test_don_nghi_chi_thu_7_chu_nhat_bi_chan(): void
    {
        $employee = $this->createEmployee();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ít nhất 1 ngày làm việc');
        $this->leaveService()->request($employee, [
            'leave_type' => 'annual',
            'start_date' => '2026-11-21', // Thứ 7.
            'end_date' => '2026-11-22', // Chủ nhật.
            'reason' => 'Nghỉ cuối tuần',
        ]);
    }

    public function test_don_nghi_thu_6_den_thu_2_ghi_2_ngay(): void
    {
        $employee = $this->createEmployee();

        $leaveRequest = $this->leaveService()->request($employee, [
            'leave_type' => 'annual',
            'start_date' => '2026-11-20', // Thứ 6.
            'end_date' => '2026-11-23', // Thứ 2.
            'reason' => 'Nghỉ cuối tuần dài',
        ]);

        $this->assertSame(2.0, (float) $leaveRequest->days);
    }

    public function test_xoa_mem_nhan_vien_bien_mat_khoi_danh_sach_nhung_con_trong_db(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($this->admin)
            ->delete(route('hr.employees.destroy', $employee))
            ->assertRedirect(route('hr.employees.index'));

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertNull(Employee::find($employee->id));
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));

        // Không còn thấy ở danh sách thường, nhưng thấy ở mục "Đã xóa".
        $this->actingAs($this->admin)->get('/hr/employees')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('employees.data', 0));
        $this->actingAs($this->admin)->get('/hr/employees?trashed=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.full_name', $employee->full_name));
    }

    public function test_vo_hieu_va_kich_hoat_lai_nhan_vien(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($this->admin)
            ->post(route('hr.employees.deactivate', $employee))
            ->assertRedirect();
        $this->assertSame(Employee::STATUS_RESIGNED, $employee->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('hr.employees.reactivate', $employee))
            ->assertRedirect();
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);
    }

    public function test_khoi_phuc_nhan_vien_da_xoa(): void
    {
        $employee = $this->createEmployee();
        $employee->delete();
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);

        $this->actingAs($this->admin)
            ->post(route('hr.employees.restore', $employee->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertNotNull(Employee::find($employee->id));
    }

    public function test_tao_nhan_vien_co_gan_tai_khoan_user(): void
    {
        $this->actingAs($this->admin)->get(route('hr.employees.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('hr.employees.store'), [
            'full_name' => 'Trần Thị Gắn TK',
            'email' => 'gantk@example.com',
            'department_id' => $this->department->id,
            'user_id' => $this->admin->id,
        ])->assertRedirect();

        $employee = Employee::where('email', 'gantk@example.com')->firstOrFail();
        $this->assertSame($this->admin->id, $employee->user_id);
    }
}
