<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Services\LeaveService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Nghỉ phép của chính mình (nhân viên tự phục vụ).
 * Chỉ xem/đăng ký đơn của hồ sơ nhân viên gắn với tài khoản hiện tại.
 */
class MyLeaveController extends Controller
{
    public function __construct(protected LeaveService $leaveService) {}

    /** Hồ sơ nhân viên gắn với tài khoản hiện tại (null nếu chưa gắn). */
    protected function myEmployee(): ?Employee
    {
        return auth()->user()->employee;
    }

    public function index(Request $request)
    {
        $employee = $this->myEmployee();

        if (! $employee) {
            return Inertia::render('SelfService/Leave/Index', [
                'employee' => null,
                'leaveRequests' => null,
                'remainingLeaveDays' => null,
            ]);
        }

        $leaveRequests = LeaveRequest::with('approver:id,name')
            ->where('employee_id', $employee->id)
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SelfService/Leave/Index', [
            'employee' => $employee->only(['id', 'code', 'full_name']),
            'leaveRequests' => $leaveRequests,
            'remainingLeaveDays' => $employee->remainingLeaveDays(),
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        $employee = $this->myEmployee();

        if (! $employee) {
            return Inertia::render('SelfService/Leave/Create', [
                'employee' => null,
                'remainingLeaveDays' => null,
            ]);
        }

        return Inertia::render('SelfService/Leave/Create', [
            'employee' => $employee->only(['id', 'code', 'full_name']),
            'remainingLeaveDays' => $employee->remainingLeaveDays(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'in:annual,sick,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'leave_type.in' => 'Loại nghỉ không hợp lệ (phép năm, ốm, không lương).',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        $employee = $this->myEmployee();

        if (! $employee) {
            return back()->with('error', 'Tài khoản chưa gắn với hồ sơ nhân viên nên không đăng ký nghỉ phép được. Vui lòng liên hệ phòng nhân sự.');
        }

        try {
            // Ép employee_id = hồ sơ của chính user, bỏ qua mọi employee_id
            // gửi lên từ client (không cho đăng ký hộ người khác).
            $leaveRequest = $this->leaveService->request($employee, $validated);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('my.leave-requests.index')
            ->with('success', "Đã gửi đơn nghỉ {$leaveRequest->code} ({$leaveRequest->days} ngày).");
    }

    /** Chỉ xem được đơn của chính mình (đơn của người khác trả 404). */
    public function show(int $id)
    {
        $employee = $this->myEmployee();

        if (! $employee) {
            abort(404);
        }

        $leaveRequest = LeaveRequest::with(['employee:id,code,full_name', 'approver:id,name'])
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        return Inertia::render('SelfService/Leave/Show', [
            'leaveRequest' => $leaveRequest,
        ]);
    }
}
