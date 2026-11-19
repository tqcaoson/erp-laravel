<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Hồi quy lỗi 9 (đợt 2, worker D): lịch chấm công theo tháng.
 * Test qua props controller trả về (month, logsByDate, approvedLeaveDates);
 * phần đánh dấu "thiếu chấm công" là logic Vue nên không test DOM.
 */
class AttendanceCalendarTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    protected User $nhanVien;

    protected Employee $myEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseData();

        $department = Department::create([
            'company_id' => $this->company->id, 'code' => 'PB-LICH', 'name' => 'Phòng kiểm thử lịch',
        ]);
        $this->nhanVien = User::where('email', 'nhanvien@erp.local')->firstOrFail();
        $this->myEmployee = Employee::create([
            'company_id' => $this->company->id, 'code' => 'NV-LICH', 'full_name' => 'Nhân Viên Lịch',
            'department_id' => $department->id, 'user_id' => $this->nhanVien->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    private function createLog(string $date, ?string $checkIn = null, ?string $checkOut = null, string $status = AttendanceLog::STATUS_PRESENT): AttendanceLog
    {
        return AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->myEmployee->id,
            'work_date' => $date,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $status,
        ]);
    }

    private function createLeave(string $code, string $start, string $end, string $status = LeaveRequest::STATUS_APPROVED): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id' => $this->company->id, 'code' => $code, 'employee_id' => $this->myEmployee->id,
            'leave_type' => LeaveRequest::TYPE_ANNUAL, 'start_date' => $start, 'end_date' => $end,
            'days' => 1, 'status' => $status,
        ]);
    }

    public function test_mac_dinh_la_thang_hien_tai(): void
    {
        $today = now()->format('Y-m-d');
        $this->createLog($today, $today.' 08:00:00', $today.' 17:00:00');

        $this->actingAs($this->nhanVien)->get('/my/attendance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('month', now()->format('Y-m'))
                ->has("logsByDate.{$today}")
            );
    }

    public function test_thang_khac_tra_dung_log_thang_do(): void
    {
        $this->createLog('2026-10-15', '2026-10-15 08:00:00', '2026-10-15 17:00:00');
        $this->createLog('2026-11-16', '2026-11-16 08:00:00', '2026-11-16 17:00:00');

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('month', '2026-10')
                ->has('logsByDate.2026-10-15')
                ->missing('logsByDate.2026-11-16')
            );
    }

    public function test_don_nghi_da_duyet_hien_trong_lich_chi_ngay_lam_viec(): void
    {
        // 14–16/10/2026 là T4–T6 → cả 3 ngày đều hiện.
        $this->createLeave('LV-DUYET-1', '2026-10-14', '2026-10-16');
        // Đơn chờ duyệt / từ chối không hiện.
        $this->createLeave('LV-CHO-1', '2026-10-19', '2026-10-19', LeaveRequest::STATUS_PENDING);
        $this->createLeave('LV-TUCHOI-1', '2026-10-20', '2026-10-20', LeaveRequest::STATUS_REJECTED);

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('approvedLeaveDates', ['2026-10-14', '2026-10-15', '2026-10-16'])
            );
    }

    public function test_don_nghi_giao_bien_thang_hien_o_ca_hai_thang_bo_thu_7_chu_nhat(): void
    {
        // 30/10/2026 là T6, 31/10 T7, 1/11 CN, 2–3/11 là T2–T3.
        $this->createLeave('LV-GIAO-1', '2026-10-30', '2026-11-03');

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('approvedLeaveDates', ['2026-10-30']));

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-11')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('approvedLeaveDates', ['2026-11-02', '2026-11-03']));
    }

    public function test_thang_sai_dinh_dang_ve_thang_hien_tai_khong_500(): void
    {
        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-13')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('month', now()->format('Y-m')));

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=khong-phai-thang')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('month', now()->format('Y-m')));
    }

    public function test_bang_lich_su_chi_tiet_van_phan_trang_nhu_cu(): void
    {
        $this->createLog('2026-10-15', '2026-10-15 08:00:00', '2026-10-15 17:00:00');

        $this->actingAs($this->nhanVien)->get('/my/attendance?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('todayLog', null)
            );
    }
}
