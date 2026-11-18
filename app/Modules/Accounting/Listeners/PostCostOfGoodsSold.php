<?php

namespace App\Modules\Accounting\Listeners;

use App\Core\Contracts\JournalPostingInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\Inventory\Events\DeliveryOrderValidated;
use App\Modules\Inventory\Models\DeliveryOrder;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phiếu giao hàng đã xác nhận → ghi nhận giá vốn hàng bán.
 * Bút toán: Nợ 632 (giá vốn) / Có 156 (hàng hóa), theo từng dòng
 * (số lượng × giá vốn đơn vị lúc xuất kho).
 */
class PostCostOfGoodsSold implements ShouldQueue
{
    public function __construct(protected JournalPostingInterface $journal) {}

    public function handle(DeliveryOrderValidated $event): void
    {
        // Job chạy nền không có session nên phải ép công ty từ event.
        CurrentCompany::setId($event->companyId);

        $lines = [];

        foreach ($event->lines as $line) {
            $amount = round((float) $line['qty'] * (float) $line['unit_cost'], 2);

            if ($amount <= 0) {
                continue;
            }

            $lines[] = ['account_code' => '632', 'debit' => $amount, 'credit' => 0];
            $lines[] = ['account_code' => '156', 'debit' => 0, 'credit' => $amount];
        }

        if ($lines === []) {
            return;
        }

        $this->journal->post([
            'entry_date' => now()->toDateString(),
            'description' => "Giá vốn hàng bán — phiếu giao #{$event->deliveryOrderId}",
            'source_type' => DeliveryOrder::class,
            'source_id' => $event->deliveryOrderId,
            'lines' => $lines,
        ]);
    }
}
