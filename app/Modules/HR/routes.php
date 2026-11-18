<?php

use App\Modules\HR\Http\Controllers\AttendanceLogController;
use App\Modules\HR\Http\Controllers\DepartmentController;
use App\Modules\HR\Http\Controllers\EmployeeApiController;
use App\Modules\HR\Http\Controllers\EmployeeController;
use App\Modules\HR\Http\Controllers\LeaveRequestApiController;
use App\Modules\HR\Http\Controllers\LeaveRequestController;
use App\Modules\HR\Http\Controllers\PayrollRunApiController;
use App\Modules\HR\Http\Controllers\PayrollRunController;
use Illuminate\Support\Facades\Route;

// Web (Inertia) — luôn có 'web' + 'auth', phân quyền theo RolePermissionSeeder.
Route::middleware(['web', 'auth'])->prefix('hr')->name('hr.')->group(function () {
    // LƯU Ý THỨ TỰ: route /employees/create phải đăng ký TRƯỚC
    // /employees/{employee}, nếu không "create" sẽ bị hiểu là id nhân viên.
    Route::middleware('permission:hr.employee.manage')->group(function () {
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    });
    Route::middleware('permission:hr.employee.view')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });

    Route::middleware('permission:hr.leave.view')->group(function () {
        Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    });
    Route::middleware('permission:hr.leave.request')->group(function () {
        Route::get('/leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
        Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    });
    Route::middleware('permission:hr.leave.approve')->group(function () {
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    Route::middleware('permission:hr.attendance.view')->group(function () {
        Route::get('/attendance', [AttendanceLogController::class, 'index'])->name('attendance.index');
    });
    Route::middleware('permission:hr.attendance.manage')->group(function () {
        Route::post('/attendance', [AttendanceLogController::class, 'store'])->name('attendance.store');
    });

    Route::middleware('permission:hr.payroll.view')->group(function () {
        Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
        Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
    });
    Route::middleware('permission:hr.payroll.run')->group(function () {
        Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
        Route::post('/payroll-runs/{payrollRun}/calculate', [PayrollRunController::class, 'calculate'])->name('payroll-runs.calculate');
        Route::post('/payroll-runs/{payrollRun}/calculate-async', [PayrollRunController::class, 'calculateAsync'])->name('payroll-runs.calculate-async');
    });
    Route::middleware('permission:hr.payroll.approve')->group(function () {
        Route::post('/payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
    });
});

// API — prefix api/v1, middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/hr')->name('api.hr.')->group(function () {
    Route::middleware('permission:hr.employee.view')->group(function () {
        Route::get('/employees', [EmployeeApiController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [EmployeeApiController::class, 'show'])->name('employees.show');
    });
    Route::middleware('permission:hr.employee.manage')->group(function () {
        Route::post('/employees', [EmployeeApiController::class, 'store'])->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeApiController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeApiController::class, 'destroy'])->name('employees.destroy');
    });

    Route::middleware('permission:hr.leave.view')->group(function () {
        Route::get('/leave-requests', [LeaveRequestApiController::class, 'index'])->name('leave-requests.index');
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestApiController::class, 'show'])->name('leave-requests.show');
    });
    Route::middleware('permission:hr.leave.request')->group(function () {
        Route::post('/leave-requests', [LeaveRequestApiController::class, 'store'])->name('leave-requests.store');
    });
    Route::middleware('permission:hr.leave.approve')->group(function () {
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestApiController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestApiController::class, 'reject'])->name('leave-requests.reject');
    });

    Route::middleware('permission:hr.payroll.view')->group(function () {
        Route::get('/payroll-runs', [PayrollRunApiController::class, 'index'])->name('payroll-runs.index');
        Route::get('/payroll-runs/{payrollRun}', [PayrollRunApiController::class, 'show'])->name('payroll-runs.show');
    });
    Route::middleware('permission:hr.payroll.run')->group(function () {
        Route::post('/payroll-runs', [PayrollRunApiController::class, 'store'])->name('payroll-runs.store');
        Route::post('/payroll-runs/{payrollRun}/calculate', [PayrollRunApiController::class, 'calculate'])->name('payroll-runs.calculate');
        Route::post('/payroll-runs/{payrollRun}/calculate-async', [PayrollRunApiController::class, 'calculateAsync'])->name('payroll-runs.calculate-async');
    });
    Route::middleware('permission:hr.payroll.approve')->group(function () {
        Route::post('/payroll-runs/{payrollRun}/lock', [PayrollRunApiController::class, 'lock'])->name('payroll-runs.lock');
    });
});
