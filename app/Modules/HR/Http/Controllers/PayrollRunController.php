<?php

namespace App\Modules\HR\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Core\Services\DocumentNumberingService;
use App\Modules\HR\Jobs\RunPayrollJob;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Requests\StorePayrollRunRequest;
use App\Modules\HR\Services\PayrollService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý kỳ lương + phiếu lương (Inertia). */
class PayrollRunController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService,
        protected DocumentNumberingService $numbering,
    ) {}

    public function index(Request $request)
    {
        $runs = PayrollRun::withCount('payslips')
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('period')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('HR/PayrollRuns/Index', [
            'payrollRuns' => $runs,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function store(StorePayrollRunRequest $request)
    {
        $run = PayrollRun::create([
            'code' => $this->numbering->next('payroll_run'),
            'period' => $request->validated()['period'],
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        return redirect()->route('hr.payroll-runs.show', $run)
            ->with('success', "Đã tạo kỳ lương {$run->period}.");
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load([
            'payslips' => fn ($q) => $q->with(['employee:id,code,full_name', 'lines'])->orderBy('employee_id'),
        ]);

        return Inertia::render('HR/PayrollRuns/Show', ['payrollRun' => $payrollRun]);
    }

    /** Tính lương đồng bộ (kỳ nhỏ); kỳ lớn nên dùng job chạy nền qua API. */
    public function calculate(PayrollRun $payrollRun)
    {
        try {
            $this->payrollService->calculate($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã tính xong lương kỳ {$payrollRun->period}.");
    }

    /** Đẩy job tính lương chạy nền (queue). */
    public function calculateAsync(PayrollRun $payrollRun)
    {
        if ($payrollRun->isLocked()) {
            return back()->with('error', 'Kỳ lương đã khóa, không thể tính lại.');
        }

        RunPayrollJob::dispatch($payrollRun->id, CurrentCompany::id());

        return back()->with('success', "Đã đưa kỳ lương {$payrollRun->period} vào hàng đợi tính lương.");
    }

    /** Khóa kỳ lương (không sửa được nữa; bút toán chỉ sinh khi duyệt). */
    public function lock(PayrollRun $payrollRun)
    {
        try {
            $this->payrollService->lock($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã khóa kỳ lương {$payrollRun->period}.");
    }

    /** Duyệt kỳ lương đã khóa (phát event cho Kế toán sinh bút toán lương). */
    public function approve(PayrollRun $payrollRun)
    {
        try {
            $this->payrollService->approve($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã duyệt kỳ lương {$payrollRun->period}. Bút toán lương đã được gửi sang module Kế toán.");
    }

    /** Đánh dấu kỳ lương đã duyệt là đã trả cho nhân viên. */
    public function pay(PayrollRun $payrollRun)
    {
        try {
            $this->payrollService->pay($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã đánh dấu trả lương kỳ {$payrollRun->period}.");
    }
}
