<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Reporting\Jobs\UpdateDailySummaryJob;
use App\Modules\Reporting\Services\DashboardService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Hóa đơn vừa ghi sổ: xóa cache KPI dashboard + cập nhật lại bảng
 * tổng hợp doanh thu của ngày hôm nay (chạy nền).
 */
class ClearReportCacheOnInvoicePosted implements ShouldQueue
{
    public function handle(InvoicePosted $event): void
    {
        DashboardService::forgetKpiCache($event->companyId);

        UpdateDailySummaryJob::dispatch($event->companyId, now()->toDateString());
    }
}
