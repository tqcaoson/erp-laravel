<?php

namespace App\Modules\Inventory\Listeners;

use App\Core\Contracts\StockServiceInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\Procurement\Events\GoodsReceiptValidated;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Khi phiếu nhận hàng được xác nhận: nhập kho từng dòng (tăng tồn). */
class IncreaseStockFromGoodsReceipt implements ShouldQueue
{
    public function __construct(
        protected StockServiceInterface $stockService,
    ) {}

    public function handle(GoodsReceiptValidated $event): void
    {
        CurrentCompany::setId($event->companyId);

        foreach ($event->lines as $line) {
            $this->stockService->receive(
                (int) $line['product_id'],
                $event->warehouseId,
                null,
                (float) $line['qty'],
                (float) $line['unit_cost'],
                'App\\Modules\\Procurement\\Models\\GoodsReceipt',
                $event->goodsReceiptId,
            );
        }
    }
}
