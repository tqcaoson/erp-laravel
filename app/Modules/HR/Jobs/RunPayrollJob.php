<?php

namespace App\Modules\HR\Jobs;

use App\Core\Services\CurrentCompany;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Services\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Job chạy tính lương cho 1 kỳ lương (chạy nền để không chặn người dùng). */
class RunPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $payrollRunId,
        public int $companyId,
    ) {}

    public function handle(PayrollService $payrollService): void
    {
        // Job chạy nền không có session nên phải ép công ty hiện tại,
        // nếu không CompanyScope sẽ không lọc và BaseModel gán sai company_id.
        CurrentCompany::setId($this->companyId);

        $run = PayrollRun::findOrFail($this->payrollRunId);

        $payrollService->calculate($run);
    }
}
