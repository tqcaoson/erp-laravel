<?php

use App\Modules\Inventory\Listeners\CreateDeliveryOrderForSalesOrder;
use App\Modules\Inventory\Listeners\IncreaseStockFromGoodsReceipt;
use App\Modules\Procurement\Events\GoodsReceiptValidated;
use App\Modules\Sales\Events\SalesOrderConfirmed;

return [
    SalesOrderConfirmed::class => [CreateDeliveryOrderForSalesOrder::class],
    GoodsReceiptValidated::class => [IncreaseStockFromGoodsReceipt::class],
];
