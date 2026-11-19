<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Chấm công của chính mình (nhân viên tự phục vụ).
 * Chỉ xem/chấm log của hồ sơ nhân viên gắn với tài khoản đang đăng nhập.
 * Chỉ chấm trong ngày hiện tại, không sửa được giờ đã chấm.
 */
class MyAttendanceController extends Controller
{
    /** Hồ sơ nhân viên gắn với tài khoản hiện tại (null nếu chưa gắn). */
    protected function myEmployee(): ?Employee
    {
        return auth()->user()->employee;
    }

    public function index(Request $request)
    {
        // Tháng hiển thị (?month=YYYY-MM). Sai định dạng thì về tháng hiện tại,
        // không 500 — tham số này do người dùng tự sửa trên URL.
        $month = $request->input('month');
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $employee = $this->myEmployee();

        if (! $employee) {
            return Inertia::render('SelfService/Attendance/Index', [
                'employee' => null,
                'logs' => null,
                'todayLog' => null,
                'month' => $month,
                'logsByDate' => (object) [],
                'approvedLeaveDates' => [],
            ]);
        }

        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Log chấm công của cả tháng (không phân trang — để vẽ lịch).
        $monthLogs = AttendanceLog::with('shift:id,name')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->orderBy('work_date')
            ->get();

        $logsByDate = $monthLogs->mapWithKeys(
            fn (AttendanceLog $log) => [$log->work_date->format('Y-m-d') => $log]
        )->all();

        // Ngày nghỉ phép đã duyệt giao với tháng (kể cả đơn bắt đầu từ tháng
        // trước hoặc kéo sang tháng sau). Chỉ lấy ngày làm việc T2–T6, nhất
        // quán với LeaveService::countDays. Chưa có bảng ngày lễ đợt này nên
        // lịch chỉ phân biệt T7/CN + đơn nghỉ đã duyệt.
        $approvedLeaveDates = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endOfMonth->toDateString())
            ->where('end_date', '>=', $startOfMonth->toDateString())
            ->get()
            ->flatMap(function (LeaveRequest $leave) use ($startOfMonth, $endOfMonth) {
                // Chỉ lấy phần đơn giao với tháng đang xem.
                $from = $leave->start_date->greaterThan($startOfMonth)
                    ? $leave->start_date->copy()->startOfDay()
                    : $startOfMonth->copy()->startOfDay();
                $to = $leave->end_date->lessThan($endOfMonth)
                    ? $leave->end_date->copy()->startOfDay()
                    : $endOfMonth->copy()->startOfDay();

                return collect(CarbonPeriod::create($from, $to))
                    ->reject(fn (Carbon $day) => $day->isWeekend())
                    ->map(fn (Carbon $day) => $day->format('Y-m-d'));
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Bảng lịch sử chi tiết (phân trang như trước, lọc 1 ngày).
        $logs = AttendanceLog::with('shift:id,name')
            ->where('employee_id', $employee->id)
            ->when($request->input('filter.work_date'), fn ($q, $date) => $q->whereDate('work_date', $date))
            ->latest('work_date')
            ->paginate(15)
            ->withQueryString();

        $todayLog = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        return Inertia::render('SelfService/Attendance/Index', [
            'employee' => $employee->only(['id', 'code', 'full_name']),
            'logs' => $logs,
            'todayLog' => $todayLog,
            'filters' => $request->only(['filter']),
            'month' => $month,
            'logsByDate' => $logsByDate,
            'approvedLeaveDates' => $approvedLeaveDates,
        ]);
    }

    /**
     * Chấm vào / chấm ra trong ngày hiện tại.
     * action = check_in | check_out. Giờ đã chấm thì không sửa lại được.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:check_in,check_out'],
        ], [
            'action.required' => 'Thiếu thao tác chấm công.',
            'action.in' => 'Thao tác chấm công không hợp lệ.',
        ]);

        $employee = $this->myEmployee();

        if (! $employee) {
            return back()->with('error', 'Tài khoản chưa gắn với hồ sơ nhân viên nên không chấm công được. Vui lòng liên hệ phòng nhân sự.');
        }

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        if ($validated['action'] === 'check_in') {
            if ($log && $log->check_in) {
                return back()->with('error', 'Bạn đã chấm vào hôm nay rồi, không chấm lại được.');
            }

            $log ??= AttendanceLog::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'work_date' => today(),
                'status' => AttendanceLog::STATUS_PRESENT,
            ]);

            $log->update(['check_in' => now()]);

            return back()->with('success', 'Đã chấm vào lúc '.now()->format('H:i').'.');
        }

        // check_out
        if (! $log || ! $log->check_in) {
            return back()->with('error', 'Bạn chưa chấm vào hôm nay nên chưa chấm ra được.');
        }

        if ($log->check_out) {
            return back()->with('error', 'Bạn đã chấm ra hôm nay rồi, không chấm lại được.');
        }

        $log->update(['check_out' => now()]);

        return back()->with('success', 'Đã chấm ra lúc '.now()->format('H:i').'.');
    }
}
