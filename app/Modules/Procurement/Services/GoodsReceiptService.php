<?php

namespace App\Modules\Procurement\Services;

use App\Core\Services\DocumentNumberingService;
use App\Modules\Procurement\Events\GoodsReceiptValidated;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu nhận hàng: tạo phiếu nháp, xác nhận (đối chiếu với PO),
 * phát event để Inventory tăng tồn và Accounting ghi nhận nhập hàng.
 */
class GoodsReceiptService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * @param  array{purchase_order_id?: int|null, supplier_id: int, warehouse_id: int, receipt_date: string, note?: string|null, lines: array<int, array{product_id: int, qty: float, unit_cost?: float, purchase_order_line_id?: int|null, lot_id?: int|null}>} $data
     */
    public function create(array $data, int $companyId): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $companyId) {
            $receipt = GoodsReceipt::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('goods_receipt', $companyId),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'receipt_date' => $data['receipt_date'],
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                GoodsReceiptLine::create([
                    'company_id' => $companyId,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                    'lot_id' => $line['lot_id'] ?? null,
                ]);
            }

            return $receipt;
        });
    }

    /**
     * Xác nhận phiếu nhận hàng: đối chiếu số lượng với PO (không cho nhận
     * vượt số lượng còn thiếu), cập nhật PO, rồi phát event
     * GoodsReceiptValidated SAU KHI commit để Inventory tăng tồn.
     */
    public function validate(int $receiptId): GoodsReceipt
    {
        $receipt = DB::transaction(function () use ($receiptId) {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receiptId);

            if ($receipt->status !== 'draft') {
                throw new DomainException('Chỉ phiếu nhận hàng ở trạng thái nháp mới được xác nhận.');
            }

            $lines = $receipt->lines()->lockForUpdate()->get();
            $receivedMap = [];

            foreach ($lines as $line) {
                if ($line->purchase_order_line_id === null) {
                    continue;
                }

                $poLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($line->purchase_order_line_id);

                $remaining = (float) $poLine->qty - (float) $poLine->received_qty;

                if ((float) $line->qty > $remaining) {
                    throw new DomainException(
                        'Số lượng nhận vượt quá số lượng đơn mua còn thiếu: dòng đơn mua #'
                        .$poLine->id.' còn thiếu '.number_format($remaining, 2, ',', '.')
                        .', phiếu nhận '.$line->qty.'.'
                    );
                }

                $receivedMap[$line->purchase_order_line_id] = ($receivedMap[$line->purchase_order_line_id] ?? 0) + (float) $line->qty;
            }

            if ($receipt->purchase_order_id !== null && $receivedMap !== []) {
                $this->purchaseOrderService->receive($receipt->purchase_order_id, $receivedMap);
            }

            $receipt->update(['status' => 'validated']);

            return $receipt->load('lines');
        });

        // Phát event sau khi commit: Inventory tăng tồn, Accounting ghi nhận.
        DB::afterCommit(fn () => GoodsReceiptValidated::dispatch(
            $receipt->id,
            $receipt->company_id,
            $receipt->purchase_order_id,
            $receipt->supplier_id,
            $receipt->warehouse_id,
            $receipt->lines->map(fn (GoodsReceiptLine $l) => [
                'product_id' => $l->product_id,
                'qty' => (float) $l->qty,
                'unit_cost' => (float) $l->unit_cost,
                'purchase_order_line_id' => $l->purchase_order_line_id,
            ])->all(),
        ));

        return $receipt;
    }

    /** Hủy phiếu nhận hàng nháp. */
    public function cancel(int $receiptId): GoodsReceipt
    {
        $receipt = GoodsReceipt::query()->findOrFail($receiptId);

        if ($receipt->status !== 'draft') {
            throw new DomainException('Chỉ phiếu nhận hàng ở trạng thái nháp mới được hủy.');
        }

        $receipt->update(['status' => 'cancelled']);

        return $receipt;
    }
}
