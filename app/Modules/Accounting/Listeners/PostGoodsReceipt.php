<?php

namespace App\Modules\Accounting\Listeners;

use App\Core\Contracts\JournalPostingInterface;
use App\Core\Services\CurrentCompany;
use App\Modules\Procurement\Events\GoodsReceiptValidated;
use App\Modules\Procurement\Models\GoodsReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phiếu nhận hàng đã xác nhận → ghi nhận hàng nhập kho.
 * Bút toán: Nợ 156 (hàng hóa) / Có 335 (hàng về chưa có hóa đơn),
 * theo tổng giá trị nhập (số lượng × đơn giá nhập của từng dòng).
 * Công nợ 331 chỉ phát sinh khi hóa đơn mua từ phiếu nhận được ghi sổ
 * (InvoiceService::post kết chuyển 335 → 331), tránh ghi trùng 156
 * nếu lập hóa đơn sau khi đã nhập kho.
 */
class PostGoodsReceipt implements ShouldQueue
{
    public function __construct(protected JournalPostingInterface $journal) {}

    public function handle(GoodsReceiptValidated $event): void
    {
        // Job chạy nền không có session nên phải ép công ty từ event.
        CurrentCompany::setId($event->companyId);

        $total = 0;

        foreach ($event->lines as $line) {
            $total += round((float) $line['qty'] * (float) $line['unit_cost'], 2);
        }

        if ($total <= 0) {
            return;
        }

        $this->journal->post([
            'entry_date' => now()->toDateString(),
            'description' => "Nhập kho hàng hóa — phiếu nhận #{$event->goodsReceiptId}",
            'source_type' => GoodsReceipt::class,
            'source_id' => $event->goodsReceiptId,
            'lines' => [
                ['account_code' => '156', 'debit' => $total, 'credit' => 0],
                ['account_code' => '335', 'debit' => 0, 'credit' => $total, 'partner_type' => 'supplier', 'partner_id' => $event->supplierId],
            ],
        ]);
    }
}
