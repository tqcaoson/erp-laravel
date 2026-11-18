<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Requests\StoreEmployeeRequest;
use App\Modules\HR\Requests\UpdateEmployeeRequest;
use Illuminate\Http\Request;

/** API nhân viên: /api/v1/hr/employees */
class EmployeeApiController extends Controller
{
    use AppliesApiQuery;

    public function index(Request $request)
    {
        $query = Employee::with(['department:id,name', 'position:id,name']);
        $this->applyApiQuery($request, $query, ['status', 'department_id', 'position_id'], ['code', 'full_name', 'created_at', 'base_salary']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($request));
    }

    public function show(Employee $employee)
    {
        return $employee->load(['department', 'position', 'contracts']);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = Employee::create([...$request->validated(), 'code' => 'TMP-'.uniqid()]);
        $employee->update(['code' => 'NV-'.str_pad($employee->id, 4, '0', STR_PAD_LEFT)]);

        return response()->json($employee->fresh(), 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return $employee->fresh();
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json(['message' => 'Đã xóa nhân viên.']);
    }
}
