<?php

namespace App\Modules\Procurement\Listeners;

use App\Core\Contracts\PurchaseRequestCreatorInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\Inventory\Events\StockBelowMinimum;
use App\Modules\Procurement\Models\PurchaseRequest;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Khi tồn xuống dưới mức tối thiểu: tạo PR gợi ý với số lượng
 * = reorder_min × 2 − tồn hiện tại (tối thiểu 1).
 * Chống trùng: đã có PR nháp cho cùng sản phẩm + kho trong 7 ngày qua thì bỏ qua.
 */
class SuggestPurchaseRequest implements ShouldQueue
{
    public function __construct(
        protected PurchaseRequestCreatorInterface $purchaseRequestCreator,
    ) {}

    public function handle(StockBelowMinimum $event): void
    {
        CurrentCompany::setId($event->companyId);

        $alreadySuggested = PurchaseRequest::query()
            ->where('company_id', $event->companyId)
            ->where('status', 'draft')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('lines', fn ($q) => $q
                ->where('product_id', $event->productId)
                ->where('warehouse_id', $event->warehouseId))
            ->exists();

        if ($alreadySuggested) {
            return;
        }

        $qty = max(1, $event->reorderMin * 2 - $event->currentQty);

        $this->purchaseRequestCreator->createSuggestion(
            $event->companyId,
            [[
                'product_id' => $event->productId,
                'qty' => $qty,
                'warehouse_id' => $event->warehouseId,
            ]],
            'Tự động gợi ý do tồn dưới mức tối thiểu (tồn hiện tại '
            .number_format($event->currentQty, 2, ',', '.')
            .', mức tối thiểu '.number_format($event->reorderMin, 2, ',', '.').').'
        );
    }
}
