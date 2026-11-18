<?php

namespace App\Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Đơn bán đã được xác nhận (draft -> confirmed).
 * Listener: Inventory tạo phiếu giao hàng, Procurement gợi ý mua phần thiếu.
 */
class SalesOrderConfirmed
{
    use Dispatchable;

    /**
     * @param  array<int, array{sales_order_line_id: int, product_id: int, qty: float, unit_price: float}> $lines
     */
    public function __construct(
        public int $salesOrderId,
        public int $companyId,
        public int $customerId,
        public int $warehouseId,
        public array $lines,
    ) {}
}
