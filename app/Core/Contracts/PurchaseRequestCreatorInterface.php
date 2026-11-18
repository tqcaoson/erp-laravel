<?php

namespace App\Core\Contracts;

/**
 * Hợp đồng tạo yêu cầu mua hàng gợi ý (PurchaseRequest).
 * Implementation: App\Modules\Procurement\Services\PurchaseRequestService.
 * Dùng bởi listener khi tồn dưới mức tối thiểu hoặc khi SO thiếu hàng giao.
 */
interface PurchaseRequestCreatorInterface
{
    /**
     * @param  array<int, array{product_id: int, qty: float, warehouse_id?: int|null, note?: string|null}> $lines
     * @return int id của PurchaseRequest vừa tạo
     */
    public function createSuggestion(int $companyId, array $lines, string $reason): int;
}
