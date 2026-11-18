<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Requests\StoreDepartmentRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý phòng ban (Inertia). */
class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::withCount('employees')
            ->with('manager:id,full_name')
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('HR/Departments/Index', [
            'departments' => $departments,
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreDepartmentRequest $request)
    {
        Department::create($request->validated());

        return redirect()->route('hr.departments.index')
            ->with('success', 'Đã tạo phòng ban mới.');
    }
}
