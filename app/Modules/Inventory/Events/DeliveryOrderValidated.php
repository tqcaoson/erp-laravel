<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phiếu giao hàng đã được thủ kho xác nhận (hàng đã xuất khỏi kho).
 * Listener: Accounting sinh bút toán giá vốn (Nợ 632 / Có 156),
 * Sales cập nhật trạng thái đơn bán.
 */
class DeliveryOrderValidated
{
    use Dispatchable;

    /**
     * @param  array<int, array{product_id: int, qty: float, unit_cost: float}> $lines
     */
    public function __construct(
        public int $deliveryOrderId,
        public int $companyId,
        public int $salesOrderId,
        public int $warehouseId,
        public array $lines,
    ) {}
}
