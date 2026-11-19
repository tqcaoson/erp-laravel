<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Reporting\Jobs\UpdateDailySummaryJob;
use App\Modules\Reporting\Services\DashboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hóa đơn vừa ghi sổ: xóa cache KPI dashboard + cập nhật lại bảng
 * tổng hợp doanh thu của ĐÚNG NGÀY hóa đơn (invoice_date có thể khác
 * ngày ghi sổ — vd ghi sổ bù hóa đơn tháng trước) thay vì luôn hôm nay.
 */
class ClearReportCacheOnInvoicePosted implements ShouldQueue
{
    public function handle(InvoicePosted $event): void
    {
        DashboardService::forgetKpiCache($event->companyId);

        $invoiceDate = DB::table('invoices')->where('id', $event->invoiceId)->value('invoice_date');

        $date = $invoiceDate
            ? Carbon::parse($invoiceDate)->toDateString()
            : now()->toDateString();

        UpdateDailySummaryJob::dispatch($event->companyId, $date);
    }
}
