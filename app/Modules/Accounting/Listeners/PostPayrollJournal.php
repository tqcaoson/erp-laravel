<?php

namespace App\Modules\Accounting\Listeners;

use App\Core\Contracts\JournalPostingInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\HR\Events\PayrollRunLocked;
use App\Modules\HR\Models\PayrollRun;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Bảng lương đã khóa → ghi nhận chi phí lương và các khoản phải trả.
 * Bút toán:
 *   Nợ 642 (chi phí lương — dùng chung 642 cho đơn giản; thực tế tách
 *            622 cho nhân công trực tiếp sản xuất, 641 cho bán hàng)
 *   Có 334 (phải trả người lao động — phần lương thực lĩnh)
 *   Có 338 (BHXH/BHYT/BHTN — phần người lao động + phần doanh nghiệp)
 *   Có 3335 (thuế thu nhập cá nhân)
 *
 * Tổng Nợ = gross + allowances + insurance_employer
 *         = net + insurance_employee + insurance_employer + tax = tổng Có.
 */
class PostPayrollJournal implements ShouldQueue
{
    public function __construct(protected JournalPostingInterface $journal) {}

    public function handle(PayrollRunLocked $event): void
    {
        // Job chạy nền không có session nên phải ép công ty từ event.
        CurrentCompany::setId($event->companyId);

        $totals = $event->totals;
        $gross = (float) ($totals['gross'] ?? 0);
        $allowances = (float) ($totals['allowances'] ?? 0);
        $insuranceEmployee = (float) ($totals['insurance_employee'] ?? 0);
        $insuranceEmployer = (float) ($totals['insurance_employer'] ?? 0);
        $tax = (float) ($totals['tax'] ?? 0);
        $net = (float) ($totals['net'] ?? 0);

        // Tổng chi phí lương doanh nghiệp chịu = lương gộp + phụ cấp + BHXH phần NSDLĐ.
        $totalCost = round($gross + $allowances + $insuranceEmployer, 2);

        if ($totalCost <= 0) {
            return;
        }

        $lines = [
            ['account_code' => '642', 'debit' => $totalCost, 'credit' => 0, 'description' => "Chi phí lương kỳ {$event->period}"],
        ];

        if ($net > 0) {
            $lines[] = ['account_code' => '334', 'debit' => 0, 'credit' => round($net, 2), 'description' => "Lương thực lĩnh kỳ {$event->period}"];
        }

        $insuranceTotal = round($insuranceEmployee + $insuranceEmployer, 2);

        if ($insuranceTotal > 0) {
            $lines[] = ['account_code' => '338', 'debit' => 0, 'credit' => $insuranceTotal, 'description' => "BHXH/BHYT/BHTN kỳ {$event->period}"];
        }

        if ($tax > 0) {
            $lines[] = ['account_code' => '3335', 'debit' => 0, 'credit' => round($tax, 2), 'description' => "Thuế TNCN kỳ {$event->period}"];
        }

        $this->journal->post([
            'entry_date' => now()->toDateString(),
            'description' => "Bút toán lương kỳ {$event->period} — bảng lương #{$event->payrollRunId}",
            'source_type' => PayrollRun::class,
            'source_id' => $event->payrollRunId,
            'lines' => $lines,
        ]);
    }
}
