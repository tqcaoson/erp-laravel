<?php

namespace App\Modules\Inventory\Services;

use App\Core\Services\CurrentCompany;
use App\Modules\Inventory\Events\DeliveryOrderValidated;
use Illuminate\Support\Facades\DB;

/**
 * Xác nhận giao hàng (phía kho).
 *
 * DeliveryOrder thuộc module Sales nên Inventory KHÔNG import model đó.
 * Service này chỉ nhận dữ liệu thuần (id + các dòng product_id/qty),
 * xuất kho thật qua StockService, rồi phát event DeliveryOrderValidated
 * với unit_cost = giá vốn bình quân tại thời điểm xuất.
 *
 * CÁCH MODULE SALES GỌI (coordinator tích hợp):
 *   // Trong Sales\Http\Controllers\DeliveryOrderController@validate
 *   // (hoặc Sales DeliveryOrderService), sau khi kiểm tra quyền +
 *   // trạng thái phiếu giao là draft:
 *   app(\App\Modules\Inventory\Services\DeliveryValidationService::class)
 *       ->validate(
 *           deliveryOrderId: $deliveryOrder->id,
 *           salesOrderId: $deliveryOrder->sales_order_id,
 *           companyId: $deliveryOrder->company_id,
 *           warehouseId: $deliveryOrder->warehouse_id,
 *           lines: $deliveryOrder->lines
 *               ->map(fn ($l) => ['product_id' => $l->product_id, 'qty' => (float) $l->qty])
 *               ->all(),
 *       );
 *   // Sau đó Sales tự cập nhật trạng thái DeliveryOrder sang validated.
 *   // Lưu ý: nên gọi service này TRƯỚC khi đổi trạng thái, vì nếu thiếu
 *   // tồn StockService sẽ ném \DomainException và phiếu giữ nguyên draft.
 */
class DeliveryValidationService
{
    /** Chuỗi nguồn ghi vào stock_moves (không import model Sales). */
    public const DELIVERY_ORDER_SOURCE = 'App\\Modules\\Sales\\Models\\DeliveryOrder';

    public function __construct(
        protected StockService $stockService,
    ) {}

    /**
     * @param  array<int, array{product_id: int, qty: float}> $lines
     * @return array<int, array{product_id: int, qty: float, unit_cost: float}> Các dòng kèm giá vốn xuất kho.
     */
    public function validate(int $deliveryOrderId, int $salesOrderId, int $companyId, int $warehouseId, array $lines): array
    {
        // Đặt công ty làm việc để CompanyScope + BaseModel gán đúng company_id
        // khi service được gọi từ queue/console.
        CurrentCompany::setId($companyId);

        $enrichedLines = DB::transaction(function () use ($deliveryOrderId, $warehouseId, $lines) {
            $result = [];

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $qty = (float) $line['qty'];

                // Giá vốn bình quân tại thời điểm xuất (lấy trước khi xuất).
                $unitCost = $this->stockService->averageCost($productId);

                $this->stockService->issue(
                    $productId,
                    $warehouseId,
                    null,
                    $qty,
                    self::DELIVERY_ORDER_SOURCE,
                    $deliveryOrderId,
                );

                $result[] = ['product_id' => $productId, 'qty' => $qty, 'unit_cost' => $unitCost];
            }

            return $result;
        });

        // Phát event SAU khi commit để listener (Accounting, Sales) chỉ chạy
        // khi hàng đã xuất kho thật.
        DB::afterCommit(fn () => DeliveryOrderValidated::dispatch(
            $deliveryOrderId,
            $companyId,
            $salesOrderId,
            $warehouseId,
            $enrichedLines,
        ));

        return $enrichedLines;
    }
}
