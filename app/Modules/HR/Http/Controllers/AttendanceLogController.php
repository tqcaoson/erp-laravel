<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Requests\StoreAttendanceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web chấm công (Inertia) + nhập tay đơn giản. */
class AttendanceLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AttendanceLog::with(['employee:id,code,full_name', 'shift:id,name'])
            ->when($request->input('filter.work_date'), fn ($q, $date) => $q->whereDate('work_date', $date))
            ->when($request->input('filter.employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('work_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('HR/AttendanceLogs/Index', [
            'logs' => $logs,
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['filter']),
        ]);
    }

    public function store(StoreAttendanceRequest $request)
    {
        AttendanceLog::updateOrCreate(
            [
                'employee_id' => $request->validated()['employee_id'],
                'work_date' => $request->validated()['work_date'],
            ],
            $request->validated(),
        );

        return redirect()->route('hr.attendance.index')
            ->with('success', 'Đã lưu bản ghi chấm công.');
    }
}
