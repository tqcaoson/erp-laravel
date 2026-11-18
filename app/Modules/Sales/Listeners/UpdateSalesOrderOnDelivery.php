<?php

namespace App\Modules\Sales\Listeners;

use App\Modules\Inventory\Events\DeliveryOrderValidated;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Khi phiếu giao được xác nhận: cộng delivered_qty cho từng dòng đơn bán
 * (khớp theo product_id), cập nhật trạng thái đơn: giao đủ hết → delivered,
 * giao một phần → partially_delivered.
 *
 * Chạy đồng bộ (không queue) để trạng thái đơn cập nhật ngay cho người dùng.
 */
class UpdateSalesOrderOnDelivery
{
    public function handle(DeliveryOrderValidated $event): void
    {
        $order = SalesOrder::query()
            ->where('id', $event->salesOrderId)
            ->where('company_id', $event->companyId)
            ->first();

        if (! $order || ! in_array($order->status, ['confirmed', 'partially_delivered'], true)) {
            return;
        }

        DB::transaction(function () use ($order, $event) {
            foreach ($event->lines as $line) {
                $remaining = (float) $line['qty'];

                // Giao cho các dòng cùng sản phẩm còn thiếu, theo thứ tự tạo.
                $orderLines = $order->lines()
                    ->where('product_id', $line['product_id'])
                    ->whereColumn('delivered_qty', '<', 'qty')
                    ->orderBy('id')
                    ->get();

                foreach ($orderLines as $orderLine) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $toAdd = min($remaining, $orderLine->remainingQty());
                    $orderLine->increment('delivered_qty', $toAdd);
                    $remaining -= $toAdd;
                }
            }

            $order->update([
                'status' => $order->fresh()->isFullyDelivered() ? 'delivered' : 'partially_delivered',
            ]);
        });
    }
}
