<?php

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Inventory\Events\DeliveryOrderValidated;
use App\Modules\Sales\Listeners\MarkSalesOrderInvoiced;
use App\Modules\Sales\Listeners\UpdateSalesOrderOnDelivery;

// Listener của module Sales: cập nhật trạng thái đơn bán khi giao hàng / lập hóa đơn.
return [
    DeliveryOrderValidated::class => [UpdateSalesOrderOnDelivery::class],
    InvoicePosted::class => [MarkSalesOrderInvoiced::class],
];
