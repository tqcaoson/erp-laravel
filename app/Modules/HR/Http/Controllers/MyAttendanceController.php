<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
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
        $employee = $this->myEmployee();

        if (! $employee) {
            return Inertia::render('SelfService/Attendance/Index', [
                'employee' => null,
                'logs' => null,
                'todayLog' => null,
            ]);
        }

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
