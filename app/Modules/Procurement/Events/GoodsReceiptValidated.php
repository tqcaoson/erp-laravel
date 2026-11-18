<?php

namespace App\Modules\Procurement\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phiếu nhận hàng đã được xác nhận (đối chiếu xong với PO).
 * Listener: Inventory tăng tồn kho, Accounting ghi nhận nhập hàng.
 */
class GoodsReceiptValidated
{
    use Dispatchable;

    /**
     * @param  array<int, array{product_id: int, qty: float, unit_cost: float, purchase_order_line_id: int|null}> $lines
     */
    public function __construct(
        public int $goodsReceiptId,
        public int $companyId,
        public ?int $purchaseOrderId,
        public int $supplierId,
        public int $warehouseId,
        public array $lines,
    ) {}
}
