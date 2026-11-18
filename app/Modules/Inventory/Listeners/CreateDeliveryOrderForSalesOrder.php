<?php

namespace App\Modules\Inventory\Listeners;

use App\Core\Contracts\DeliveryOrderCreatorInterface;
use App\Core\Contracts\PurchaseRequestCreatorInterface;
use App\Core\Contracts\StockServiceInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Khi đơn bán được xác nhận: với mỗi dòng, kiểm tra tồn khả dụng tại kho
 * của đơn — phần đủ tồn thì tạo phiếu giao hàng (qua interface của Sales),
 * phần thiếu tồn thì tạo gợi ý yêu cầu mua hàng (qua interface Procurement).
 * Dòng nào không đủ tồn chút nào thì bỏ qua (không tạo DO cho dòng đó).
 */
class CreateDeliveryOrderForSalesOrder implements ShouldQueue
{
    public function __construct(
        protected StockServiceInterface $stockService,
        protected PurchaseRequestCreatorInterface $purchaseRequestCreator,
    ) {}

    public function handle(SalesOrderConfirmed $event): void
    {
        CurrentCompany::setId($event->companyId);

        $deliveryLines = [];
        $shortageLines = [];

        foreach ($event->lines as $line) {
            $productId = (int) $line['product_id'];
            $qty = (float) $line['qty'];

            $available = $this->stockService->availableQty($productId, $event->warehouseId);
            $deliverQty = min($available, $qty);
            $shortQty = $qty - $available;

            if ($deliverQty > 0) {
                $deliveryLines[] = [
                    'sales_order_line_id' => (int) $line['sales_order_line_id'],
                    'product_id' => $productId,
                    'qty' => $deliverQty,
                    'warehouse_id' => $event->warehouseId,
                ];
            }

            if ($shortQty > 0) {
                $shortageLines[] = [
                    'product_id' => $productId,
                    'qty' => $shortQty,
                    'warehouse_id' => $event->warehouseId,
                    'note' => 'Thiếu tồn cho đơn bán #'.$event->salesOrderId,
                ];
            }
        }

        if ($deliveryLines !== []) {
            // Implementation thuộc module Sales; resolve lười để listener vẫn
            // chạy được nếu Sales chưa đăng ký binding (ghi log và bỏ qua).
            try {
                $creator = app(DeliveryOrderCreatorInterface::class);
                $creator->createForSalesOrder($event->salesOrderId, $deliveryLines);
            } catch (\Throwable $e) {
                Log::warning('Không tạo được phiếu giao hàng cho đơn bán #'.$event->salesOrderId.': '.$e->getMessage());
            }
        }

        if ($shortageLines !== []) {
            $this->purchaseRequestCreator->createSuggestion(
                $event->companyId,
                $shortageLines,
                'Tự động gợi ý do đơn bán #'.$event->salesOrderId.' thiếu tồn kho tại kho #'.$event->warehouseId.'.'
            );
        }
    }
}
