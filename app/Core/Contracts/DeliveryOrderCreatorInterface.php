<?php

namespace App\Core\Contracts;

/**
 * Hợp đồng tạo phiếu giao hàng (DeliveryOrder) từ đơn bán.
 * Implementation: App\Modules\Sales\Services\DeliveryOrderService.
 * Dùng bởi listener của Inventory khi nhận event SalesOrderConfirmed.
 */
interface DeliveryOrderCreatorInterface
{
    /**
     * @param  int   $salesOrderId
     * @param  array<int, array{sales_order_line_id: int, product_id: int, qty: float, warehouse_id: int}> $lines
     * @return int id của DeliveryOrder vừa tạo
     */
    public function createForSalesOrder(int $salesOrderId, array $lines): int;
}
