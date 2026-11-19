<?php

namespace App\Modules\HR\Http\Controllers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Phiếu lương của chính mình (nhân viên tự phục vụ).
 * Chỉ truy vấn phiếu lương của hồ sơ nhân viên gắn với tài khoản hiện
 * tại — không tái dùng PayrollRunController::show (trang đó load toàn
 * bộ phiếu lương của cả kỳ).
 */
class MyPayslipController extends Controller
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
            return Inertia::render('SelfService/Payslips/Index', [
                'employee' => null,
                'payslips' => null,
            ]);
        }

        // Nhân viên chỉ xem được phiếu lương của kỳ đã khóa trở đi
        // (locked/approved/paid) — kỳ còn nháp hoặc mới tính xong thì
        // chưa chốt, chưa cho xem.
        $payslips = Payslip::with('payrollRun:id,code,period,status')
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', PayrollRun::VISIBLE_TO_EMPLOYEE_STATUSES))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SelfService/Payslips/Index', [
            'employee' => $employee->only(['id', 'code', 'full_name']),
            'payslips' => $payslips,
        ]);
    }

    /** Chỉ xem được phiếu lương của chính mình (của người khác trả 404). */
    public function show(int $id)
    {
        $employee = $this->myEmployee();

        if (! $employee) {
            abort(404);
        }

        // Phiếu lương của kỳ chưa khóa thì chưa được xem (404 như
        // phiếu của người khác — không lộ sự tồn tại của phiếu).
        $payslip = Payslip::with(['payrollRun:id,code,period,status', 'employee:id,code,full_name', 'lines'])
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', PayrollRun::VISIBLE_TO_EMPLOYEE_STATUSES))
            ->findOrFail($id);

        return Inertia::render('SelfService/Payslips/Show', [
            'payslip' => $payslip,
        ]);
    }
}
