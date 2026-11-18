<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Requests\StoreLeaveRequestRequest;
use App\Modules\HR\Services\LeaveService;
use DomainException;
use Illuminate\Http\Request;

/** API nghỉ phép: /api/v1/hr/leave-requests */
class LeaveRequestApiController extends Controller
{
    use AppliesApiQuery;

    public function __construct(protected LeaveService $leaveService) {}

    public function index(Request $request)
    {
        $query = LeaveRequest::with(['employee:id,code,full_name', 'approver:id,name']);
        $this->applyApiQuery($request, $query, ['status', 'leave_type', 'employee_id'], ['start_date', 'created_at', 'days']);

        return $query->paginate($this->perPage($request));
    }

    public function show(LeaveRequest $leaveRequest)
    {
        return $leaveRequest->load(['employee', 'approver']);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $employee = Employee::findOrFail($request->validated()['employee_id']);

        try {
            $leaveRequest = $this->leaveService->request($employee, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($leaveRequest, 201);
    }

    public function approve(LeaveRequest $leaveRequest)
    {
        try {
            $leaveRequest = $this->leaveService->approve($leaveRequest, auth()->id());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $leaveRequest;
    }

    public function reject(LeaveRequest $leaveRequest)
    {
        try {
            $leaveRequest = $this->leaveService->reject($leaveRequest, auth()->id());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $leaveRequest;
    }
}
