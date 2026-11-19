<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveBalance;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy cho phân quyền + quản lý tài khoản + nhân viên tự phục vụ
 * (worker C, 19-20/11/2026):
 * - Role nhan_vien chỉ vào được trang "của mình", bị 403 ở trang HR chung,
 *   không xem được phiếu lương của người khác.
 * - Tạo user mới đăng nhập được; vô hiệu thì đăng nhập bị chặn; đang
 *   đăng nhập mà bị vô hiệu thì request tiếp theo bị đá ra.
 * - Admin không tự vô hiệu / tự xóa chính mình.
 */
class SelfServiceAndUserManagementTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    protected Department $department;

    protected User $nhanVien;

    protected Employee $myEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseData();
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'PB-TEST',
            'name' => 'Phòng kiểm thử',
        ]);

        // Tài khoản nhan_vien demo + hồ sơ nhân viên gắn với tài khoản đó.
        $this->nhanVien = User::where('email', 'nhanvien@erp.local')->firstOrFail();
        $this->myEmployee = $this->createEmployee('NV-MINH', 'Nhân Viên Của Tôi', $this->nhanVien->id);
    }

    protected function createEmployee(string $code, string $name, ?int $userId = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'full_name' => $name,
            'department_id' => $this->department->id,
            'user_id' => $userId,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    protected function createPayslip(Employee $employee, string $period = '2026-11'): Payslip
    {
        $run = PayrollRun::firstOrCreate(
            ['company_id' => $this->company->id, 'period' => $period],
            ['code' => 'BL-'.$period, 'status' => PayrollRun::STATUS_CALCULATED],
        );

        return Payslip::create([
            'company_id' => $this->company->id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'base_salary' => 10000000,
            'allowances' => 1000000,
            'net_salary' => 11000000,
            'status' => 'draft',
        ]);
    }

    public function test_nhan_vien_vao_duoc_cac_trang_cua_minh(): void
    {
        $this->actingAs($this->nhanVien)->get('/my/attendance')->assertOk();
        $this->actingAs($this->nhanVien)->get('/my/leave-requests')->assertOk();
        $this->actingAs($this->nhanVien)->get('/my/leave-requests/create')->assertOk();
        $this->actingAs($this->nhanVien)->get('/my/payslips')->assertOk();
        $this->actingAs($this->nhanVien)->get('/dashboard')->assertOk();
    }

    public function test_nhan_vien_bi_403_o_trang_hr_chung(): void
    {
        $this->actingAs($this->nhanVien)->get('/hr/payroll-runs')->assertForbidden();
        $this->actingAs($this->nhanVien)->get('/hr/employees')->assertForbidden();
        $this->actingAs($this->nhanVien)->get('/hr/leave-requests')->assertForbidden();
        $this->actingAs($this->nhanVien)->get('/hr/attendance')->assertForbidden();
        $this->actingAs($this->nhanVien)->get('/admin/users')->assertForbidden();
    }

    public function test_nhan_vien_khong_xem_duoc_phieu_luong_cua_nguoi_khac(): void
    {
        $other = $this->createEmployee('NV-KHAC', 'Nhân Viên Khác');
        $otherPayslip = $this->createPayslip($other);
        $myPayslip = $this->createPayslip($this->myEmployee);

        $this->actingAs($this->nhanVien)
            ->get(route('my.payslips.show', $myPayslip))
            ->assertOk();

        $this->actingAs($this->nhanVien)
            ->get(route('my.payslips.show', $otherPayslip))
            ->assertNotFound();

        // Danh sách của mình cũng không lộ phiếu lương người khác.
        $this->actingAs($this->nhanVien)
            ->get('/my/payslips')
            ->assertOk()
            // Không dùng ->component() ở đây: Inertia mặc định tìm page
            // ở js/pages (chữ thường) trong khi project dùng js/Pages.
            ->assertInertia(fn ($page) => $page
                ->has('payslips.data', 1)
                ->where('payslips.data.0.id', $myPayslip->id)
            );
    }

    public function test_nhan_vien_cham_cong_vao_ra_trong_ngay(): void
    {
        $this->actingAs($this->nhanVien)
            ->post(route('my.attendance.store'), ['action' => 'check_in'])
            ->assertRedirect();

        $this->assertTrue(
            \App\Modules\HR\Models\AttendanceLog::where('employee_id', $this->myEmployee->id)
                ->whereDate('work_date', today())
                ->exists()
        );

        // Chấm vào lần 2 bị từ chối.
        $this->actingAs($this->nhanVien)
            ->post(route('my.attendance.store'), ['action' => 'check_in'])
            ->assertRedirect();

        $this->actingAs($this->nhanVien)
            ->post(route('my.attendance.store'), ['action' => 'check_out'])
            ->assertRedirect();

        $log = \App\Modules\HR\Models\AttendanceLog::where('employee_id', $this->myEmployee->id)->firstOrFail();
        $this->assertNotNull($log->check_in);
        $this->assertNotNull($log->check_out);

        // Chấm ra lần 2 bị từ chối.
        $this->actingAs($this->nhanVien)
            ->post(route('my.attendance.store'), ['action' => 'check_out'])
            ->assertRedirect();
    }

    public function test_nhan_vien_dang_ky_nghi_phep_cho_chinh_minh(): void
    {
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->myEmployee->id,
            'year' => 2026,
            'total_days' => 12,
            'used_days' => 0,
        ]);

        $other = $this->createEmployee('NV-KHAC2', 'Nhân Viên Khác 2');

        // Gửi kèm employee_id của người khác: hệ thống phải ép về hồ sơ của mình.
        $this->actingAs($this->nhanVien)
            ->post(route('my.leave-requests.store'), [
                'employee_id' => $other->id,
                'leave_type' => 'annual',
                'start_date' => '2026-11-23', // Thứ 2.
                'end_date' => '2026-11-23',
                'reason' => 'Nghỉ phép năm',
            ])
            ->assertRedirect(route('my.leave-requests.index'));

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->myEmployee->id,
            'leave_type' => 'annual',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('leave_requests', [
            'employee_id' => $other->id,
        ]);
    }

    public function test_tai_khoan_chua_gan_nhan_vien_thay_thong_bao_chu_khong_500(): void
    {
        $this->actingAs($this->nhanVien)->get('/my/attendance')->assertOk();

        $unlinked = $this->createUserWithRole('chuagan@erp.local', 'nhan_vien');

        // Trang vẫn 200 với employee = null để Vue hiện thông báo
        // "Tài khoản chưa gắn với hồ sơ nhân viên" thay vì 500.
        // (Không dùng ->component(): xem ghi chú ở test phiếu lương.)
        $this->actingAs($unlinked)->get('/my/attendance')->assertOk()
            ->assertInertia(fn ($page) => $page->where('employee', null));
        $this->actingAs($unlinked)->get('/my/leave-requests')->assertOk()
            ->assertInertia(fn ($page) => $page->where('employee', null));
        $this->actingAs($unlinked)->get('/my/payslips')->assertOk()
            ->assertInertia(fn ($page) => $page->where('employee', null));
    }

    public function test_tao_user_moi_dang_nhap_duoc(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Người Dùng Mới',
                'email' => 'moi@erp.local',
                'password' => 'matkhau123',
                'roles' => [\App\Core\Models\Role::where('name', 'nhan_vien')->firstOrFail()->id],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'moi@erp.local', 'is_active' => true]);

        // Đăng xuất admin trước: actingAs giữ user admin trong guard nên
        // phải logout thì lần đăng nhập sau mới đổi được user hiện tại.
        $this->post('/logout');

        $this->post('/login', ['email' => 'moi@erp.local', 'password' => 'matkhau123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'moi@erp.local')->firstOrFail());
    }

    public function test_vo_hieu_xong_dang_nhap_bi_chan(): void
    {
        $user = User::create([
            'name' => 'Bị Vô Hiệu',
            'email' => 'vohieu@erp.local',
            'password' => Hash::make('password'),
            'current_company_id' => $this->company->id,
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => 'vohieu@erp.local', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Kích hoạt lại thì đăng nhập được.
        $user->update(['is_active' => true]);
        $this->post('/login', ['email' => 'vohieu@erp.local', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_dang_dang_nhap_bi_vo_hieu_thi_request_tiep_theo_bi_da_ra(): void
    {
        $this->actingAs($this->nhanVien)->get('/my/attendance')->assertOk();

        $this->nhanVien->update(['is_active' => false]);

        $this->actingAs($this->nhanVien)->get('/my/attendance')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_tu_vo_hieu_chinh_minh_bi_chan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-active', $this->admin))
            ->assertRedirect();
        $this->assertTrue($this->admin->fresh()->is_active);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'is_active' => false,
            ])
            ->assertRedirect();
        $this->assertTrue($this->admin->fresh()->is_active);

        // Tự xóa chính mình cũng bị chặn.
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertRedirect();
        $this->assertNotNull(User::find($this->admin->id));
    }

    public function test_admin_vo_hieu_va_xoa_duoc_user_khac(): void
    {
        $target = $this->createUserWithRole('xoatam@erp.local', 'nhan_vien');

        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-active', $target))
            ->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));
        $this->assertNull(User::find($target->id));
    }

    public function test_ke_toan_xem_duoc_bang_luong_kho_xem_duoc_nha_cung_cap(): void
    {
        $this->actingAs(User::where('email', 'ketoan@erp.local')->firstOrFail())
            ->get('/hr/payroll-runs')->assertOk();

        $this->actingAs(User::where('email', 'kho@erp.local')->firstOrFail())
            ->get('/procurement/suppliers')->assertOk();
    }
}
