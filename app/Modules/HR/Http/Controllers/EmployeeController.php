<?php

namespace App\Modules\HR\Http\Controllers;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Requests\StoreEmployeeRequest;
use App\Modules\HR\Requests\UpdateEmployeeRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý nhân viên (Inertia). */
class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'position'])
            // Lọc "Đã xóa": chỉ liệt kê nhân viên đã xóa mềm (để khôi phục).
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($request->input('search'), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('filter.department_id'), fn ($q, $id) => $q->where('department_id', $id));

        $sort = (string) $request->input('sort', '-created_at');
        $sortColumn = ltrim($sort, '-');
        if (! in_array($sortColumn, ['code', 'full_name', 'created_at', 'base_salary'], true)) {
            $sortColumn = 'created_at';
        }
        $query->orderBy($sortColumn, str_starts_with($sort, '-') ? 'desc' : 'asc');

        return Inertia::render('HR/Employees/Index', [
            'employees' => $query->paginate(15)->withQueryString(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'filter', 'sort', 'trashed']),
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create()
    {
        return Inertia::render('HR/Employees/Create', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        // Cột code bắt buộc nên tạo tạm bằng mã duy nhất, rồi gán mã chính
        // thức NV-0001... sau khi đã có id.
        $employee = Employee::create([
            ...$request->validated(),
            'code' => 'TMP-'.uniqid(),
        ]);

        Employee::assignCode($employee);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', "Đã tạo nhân viên {$employee->full_name} ({$employee->code}).");
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'position', 'contracts', 'leaveRequests' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('HR/Employees/Show', [
            'employee' => $employee,
            'remainingLeaveDays' => $employee->remainingLeaveDays(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('HR/Employees/Edit', [
            'employee' => $employee,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete(); // Soft delete.

        return redirect()->route('hr.employees.index')
            ->with('success', "Đã xóa nhân viên {$employee->full_name}.");
    }

    /** Cho nhân viên nghỉ việc (vô hiệu: status → resigned, giữ lại hồ sơ). */
    public function deactivate(Employee $employee)
    {
        $employee->update(['status' => Employee::STATUS_RESIGNED]);

        return redirect()->back()
            ->with('success', "Đã cho nhân viên {$employee->full_name} nghỉ việc.");
    }

    /** Kích hoạt lại nhân viên đã nghỉ việc (status → active). */
    public function reactivate(Employee $employee)
    {
        $employee->update(['status' => Employee::STATUS_ACTIVE]);

        return redirect()->back()
            ->with('success', "Đã kích hoạt lại nhân viên {$employee->full_name}.");
    }

    /**
     * Khôi phục nhân viên đã xóa mềm.
     * Nhận id thô (không dùng route model binding với class Employee)
     * vì binding mặc định không tìm thấy bản ghi đã xóa mềm.
     */
    public function restore(int $employee)
    {
        $employee = Employee::withTrashed()->findOrFail($employee);
        $employee->restore();

        return redirect()->back()
            ->with('success', "Đã khôi phục nhân viên {$employee->full_name}.");
    }
}
