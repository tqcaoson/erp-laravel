<?php

use App\Modules\Accounting\Listeners\PostCostOfGoodsSold;
use App\Modules\Accounting\Listeners\PostGoodsReceipt;
use App\Modules\Accounting\Listeners\PostPayrollJournal;
use App\Modules\HR\Events\PayrollRunApproved;
use App\Modules\Inventory\Events\DeliveryOrderValidated;
use App\Modules\Procurement\Events\GoodsReceiptValidated;

/**
 * Đăng ký listener cho các domain event xuyên module mà Kế toán lắng nghe.
 * ModuleServiceProvider tự nạp file này (không cần sửa provider).
 */
return [
    DeliveryOrderValidated::class => [PostCostOfGoodsSold::class],
    GoodsReceiptValidated::class => [PostGoodsReceipt::class],
    PayrollRunApproved::class => [PostPayrollJournal::class],
];
