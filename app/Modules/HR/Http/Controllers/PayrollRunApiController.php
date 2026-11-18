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

/** API bảng lương: /api/v1/hr/payroll-runs */
class PayrollRunApiController extends Controller
{
    use AppliesApiQuery;

    public function __construct(
        protected PayrollService $payrollService,
        protected DocumentNumberingService $numbering,
    ) {}

    public function index(Request $request)
    {
        $query = PayrollRun::withCount('payslips');
        $this->applyApiQuery($request, $query, ['status', 'period'], ['period', 'created_at']);

        return $query->paginate($this->perPage($request));
    }

    public function show(PayrollRun $payrollRun)
    {
        return $payrollRun->load([
            'payslips' => fn ($q) => $q->with(['employee:id,code,full_name', 'lines']),
        ]);
    }

    public function store(StorePayrollRunRequest $request)
    {
        $run = PayrollRun::create([
            'code' => $this->numbering->next('payroll_run'),
            'period' => $request->validated()['period'],
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        return response()->json($run, 201);
    }

    /** Tính lương đồng bộ. Kỳ lớn nên dùng calculate-async (job chạy nền). */
    public function calculate(PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollService->calculate($payrollRun);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $run;
    }

    /** Đẩy job tính lương chạy nền. */
    public function calculateAsync(PayrollRun $payrollRun)
    {
        if ($payrollRun->isLocked()) {
            return response()->json(['message' => 'Kỳ lương đã khóa, không thể tính lại.'], 409);
        }

        RunPayrollJob::dispatch($payrollRun->id, CurrentCompany::id());

        return response()->json(['message' => "Đã đưa kỳ lương {$payrollRun->period} vào hàng đợi tính lương."]);
    }

    /** Khóa kỳ lương (phát event PayrollRunLocked cho Kế toán). */
    public function lock(PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollService->lock($payrollRun);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $run;
    }
}
