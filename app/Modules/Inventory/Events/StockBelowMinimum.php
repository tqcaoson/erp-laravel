<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tồn kho của 1 sản phẩm tại 1 kho xuống dưới mức tối thiểu (reorder_min).
 * Listener: Procurement tạo gợi ý yêu cầu mua hàng.
 */
class StockBelowMinimum
{
    use Dispatchable;

    public function __construct(
        public int $productId,
        public int $companyId,
        public int $warehouseId,
        public float $currentQty,
        public float $reorderMin,
    ) {}
}
