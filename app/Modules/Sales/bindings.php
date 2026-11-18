<?php

use App\Core\Contracts\DeliveryOrderCreatorInterface;
use App\Modules\Sales\Services\DeliveryOrderService;

// Sales implement hợp đồng tạo phiếu giao hàng (Inventory gọi khi nhận SalesOrderConfirmed).
return [
    DeliveryOrderCreatorInterface::class => DeliveryOrderService::class,
];
