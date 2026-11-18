<?php

namespace App\Modules\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Hóa đơn đã được ghi sổ (posted).
 * Listener: Sales đánh dấu đơn bán đã lập hóa đơn,
 * Reporting xóa cache báo cáo doanh thu.
 */
class InvoicePosted
{
    use Dispatchable;

    public function __construct(
        public int $invoiceId,
        public int $companyId,
        public string $type, // 'sale' | 'purchase'
        public ?string $sourceType,
        public ?int $sourceId,
        public ?int $customerId,
        public ?int $supplierId,
        public float $totalAmount,
    ) {}
}
