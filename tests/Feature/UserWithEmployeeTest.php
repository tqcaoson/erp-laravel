<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy lỗi 10 (đợt 2): admin tạo tài khoản kèm luôn hồ sơ nhân viên
 * để nhân viên mới đăng nhập là chấm công/nghỉ phép được ngay, không
 * còn banner "chưa gắn hồ sơ".
 *
 * Phụ thuộc Employee::assignCode() do worker A thêm vào Employee.php —
 * các test tạo kèm hồ sơ sẽ lỗi cho tới khi method đó tồn tại.
 */
class UserWithEmployeeTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private function createDepartment(): Department
    {
        return Department::create([
            'company_id' => $this->company->id,
            'code' => 'PB-TEST',
            'name' => 'Phòng kiểm thử',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::create([
            'company_id' => $this->company->id,
            'code' => 'CV-TEST',
            'name' => 'Chức danh kiểm thử',
        ]);
    }

    /** Payload tạo tài khoản kèm hồ sơ, có thể ghi đè từng trường. */
    private function payload(array $overrides = []): array
    {
        $department = $this->createDepartment();

        return array_merge([
            'name' => 'Nguyễn Văn Mới',
            'email' => 'moi@erp.local',
            'password' => 'matkhau123',
            'roles' => [],
            'is_active' => true,
            'create_employee' => true,
            'employee' => [
                'department_id' => $department->id,
                'position_id' => null,
                'phone' => '0901234567',
                'hire_date' => '2026-11-19',
                'base_salary' => null,
            ],
        ], $overrides);
    }

    public function test_tạo_tài_khoản_kèm_hồ_sơ_nhân_viên(): void
    {
        $this->seedBaseData();
        $position = $this->createPosition();
        $payload = $this->payload();
        $payload['employee']['position_id'] = $position->id;

        $response = $this->actingAs($this->admin)->post('/admin/users', $payload);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'moi@erp.local')->firstOrFail();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Nguyễn Văn Mới', $employee->full_name);
        $this->assertSame('moi@erp.local', $employee->email);
        $this->assertSame($payload['employee']['department_id'], $employee->department_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame('active', $employee->status);
        $this->assertMatchesRegularExpression('/^NV-\d{4}$/', $employee->code);
        // Lương cơ bản để trống thì mặc định 0.
        $this->assertEquals(0, (float) $employee->base_salary);
        // Thông báo nêu cả tài khoản + mã NV.
        $response->assertSessionHas('success', "Đã tạo tài khoản moi@erp.local và hồ sơ nhân viên {$employee->code}.");
    }

    public function test_lương_cơ_bản_nhập_số_thì_lưu_đúng(): void
    {
        $this->seedBaseData();
        $payload = $this->payload();
        $payload['employee']['base_salary'] = 15000000;

        $this->actingAs($this->admin)->post('/admin/users', $payload)->assertRedirect();

        $user = User::where('email', 'moi@erp.local')->firstOrFail();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertEquals(15000000, (float) $employee->base_salary);
    }

    public function test_tài_khoản_mới_có_hồ_sơ_thì_chấm_công_không_còn_banner_chưa_gắn(): void
    {
        $this->seedBaseData();

        // Gán role nhân viên để có quyền hr.attendance.self vào trang.
        $payload = $this->payload();
        $payload['roles'] = [\App\Core\Models\Role::where('name', 'nhan_vien')->firstOrFail()->id];

        $this->actingAs($this->admin)->post('/admin/users', $payload)->assertRedirect();

        $this->post('/logout');
        $this->post('/login', ['email' => 'moi@erp.local', 'password' => 'matkhau123'])
            ->assertRedirect(route('dashboard'));

        // Prop employee khác null => Vue không hiện banner "chưa gắn".
        $this->get('/my/attendance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('employee')->where('employee.code', fn ($code) => str_starts_with($code, 'NV-')));
    }

    public function test_thiếu_phòng_ban_thì_validate_lỗi_và_không_tạo_user(): void
    {
        $this->seedBaseData();
        $payload = $this->payload();
        unset($payload['employee']['department_id']);

        $this->actingAs($this->admin)
            ->post('/admin/users', $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('employee.department_id');

        // Transaction rollback: không có user nào được tạo.
        $this->assertDatabaseMissing('users', ['email' => 'moi@erp.local']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_không_tick_tạo_hồ_sơ_thì_hành_vi_cũ_nguyên_vẹn(): void
    {
        $this->seedBaseData();
        $payload = $this->payload();
        unset($payload['create_employee'], $payload['employee']);

        $this->actingAs($this->admin)
            ->post('/admin/users', $payload)
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'moi@erp.local']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_form_tạo_user_truyền_departments_và_positions(): void
    {
        $this->seedBaseData();
        $this->createDepartment();
        $this->createPosition();

        $this->actingAs($this->admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('departments', 1)
                ->has('positions', 1)
                ->has('roles'));
    }
}
