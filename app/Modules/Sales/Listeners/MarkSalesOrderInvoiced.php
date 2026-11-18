<?php

namespace App\Modules\Sales\Listeners;

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Sales\Models\SalesOrder;

/**
 * Khi hóa đơn bán được ghi sổ (type = sale, nguồn là SalesOrder):
 * đơn bán ở trạng thái delivered → invoiced.
 */
class MarkSalesOrderInvoiced
{
    public function handle(InvoicePosted $event): void
    {
        if ($event->type !== 'sale') {
            return;
        }

        // sourceType là tên class model nguồn (SalesOrder) — chấp nhận cả dạng ngắn 'sales_order'.
        if (! in_array($event->sourceType, [SalesOrder::class, 'sales_order', 'App\\Modules\\Sales\\Models\\SalesOrder'], true)) {
            return;
        }

        if (! $event->sourceId) {
            return;
        }

        SalesOrder::query()
            ->where('id', $event->sourceId)
            ->where('company_id', $event->companyId)
            ->where('status', 'delivered')
            ->update(['status' => 'invoiced']);
    }
}
