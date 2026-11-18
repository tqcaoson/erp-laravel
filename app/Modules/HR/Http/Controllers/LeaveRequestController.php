<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Requests\StoreLeaveRequestRequest;
use App\Modules\HR\Services\LeaveService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý nghỉ phép (Inertia). */
class LeaveRequestController extends Controller
{
    public function __construct(protected LeaveService $leaveService) {}

    public function index(Request $request)
    {
        $leaveRequests = LeaveRequest::with(['employee:id,code,full_name', 'approver:id,name'])
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('filter.employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('HR/LeaveRequests/Index', [
            'leaveRequests' => $leaveRequests,
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        $employees = Employee::where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'code'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'code' => $e->code,
                'remaining_leave_days' => $e->remainingLeaveDays(),
            ]);

        return Inertia::render('HR/LeaveRequests/Create', ['employees' => $employees]);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $employee = Employee::findOrFail($request->validated()['employee_id']);

        try {
            $leaveRequest = $this->leaveService->request($employee, $request->validated());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.leave-requests.index')
            ->with('success', "Đã tạo đơn nghỉ {$leaveRequest->code} ({$leaveRequest->days} ngày).");
    }

    public function approve(LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveService->approve($leaveRequest, auth()->id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã duyệt đơn nghỉ {$leaveRequest->code}.");
    }

    public function reject(LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveService->reject($leaveRequest, auth()->id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã từ chối đơn nghỉ {$leaveRequest->code}.");
    }
}
