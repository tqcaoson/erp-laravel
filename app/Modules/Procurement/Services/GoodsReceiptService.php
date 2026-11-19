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

    /**
     * Lập hóa đơn mua nháp từ phần đã nhận chưa lập hóa đơn của phiếu nhận.
     *
     * Mỗi dòng hóa đơn lấy (qty − invoiced_qty) × đơn giá nhập của dòng
     * phiếu nhận. Dòng đơn mua hiện không có thuế suất riêng theo dòng
     * (thuế nằm ở cấp đơn mua) nên thuế suất mặc định 0; kế toán bổ sung
     * thuế khi sửa hóa đơn nháp nếu hóa đơn thực tế có thuế GTGT.
     * Hóa đơn gắn source_type/source_id về phiếu nhận để khi ghi sổ,
     * InvoiceService kết chuyển 335 → 331 thay vì ghi trùng 156.
     *
     * @return int id hóa đơn nháp vừa tạo
     *
     * @throws DomainException khi phiếu chưa xác nhận, đã lập hóa đơn hết
     *                         phần đã nhận, hoặc module Kế toán chưa khả dụng.
     */
    public function createInvoiceFromReceipt(GoodsReceipt $receipt): int
    {
        if ($receipt->status !== 'validated') {
            throw new DomainException('Phiếu nhận hàng chưa được xác nhận nên chưa lập được hóa đơn mua.');
        }

        $receipt->loadMissing(['lines', 'supplier']);

        $billable = [];
        foreach ($receipt->lines as $line) {
            $qty = (float) $line->qty - (float) ($line->invoiced_qty ?? 0);

            if ($qty > 0) {
                $billable[] = ['line' => $line, 'qty' => $qty];
            }
        }

        if ($billable === []) {
            throw new DomainException('Phiếu nhận hàng không còn phần nào chưa lập hóa đơn.');
        }

        if (! class_exists(\App\Modules\Accounting\Services\InvoiceService::class)) {
            throw new DomainException('Module Kế toán chưa khả dụng nên chưa lập được hóa đơn.');
        }

        // Tên sản phẩm cho diễn giải dòng hóa đơn (đọc bằng query builder,
        // không import model của Inventory — theo quy tắc phụ thuộc module).
        $productNames = DB::table('products')
            ->whereIn('id', collect($billable)->map(fn ($item) => (int) $item['line']->product_id)->all())
            ->pluck('name', 'id');

        $invoiceLines = [];
        foreach ($billable as $item) {
            $line = $item['line'];
            $name = $productNames[(int) $line->product_id] ?? ('Sản phẩm #'.$line->product_id);

            $invoiceLines[] = [
                'product_id' => (int) $line->product_id,
                'description' => $name,
                'qty' => $item['qty'],
                'unit_price' => (float) $line->unit_cost,
                'tax_rate' => 0,
            ];
        }

        $dueDate = null;
        $termDays = (int) ($receipt->supplier->payment_term_days ?? 0);
        if ($termDays > 0) {
            $dueDate = now()->addDays($termDays)->toDateString();
        }

        return DB::transaction(function () use ($receipt, $billable, $invoiceLines, $dueDate) {
            $invoice = app(\App\Modules\Accounting\Services\InvoiceService::class)->create([
                'type' => 'purchase',
                'supplier_id' => (int) $receipt->supplier_id,
                'source_type' => GoodsReceipt::class,
                'source_id' => $receipt->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'lines' => $invoiceLines,
            ], (int) $receipt->company_id);

            // Ghi nhận phần vừa lập hóa đơn để lần sau không lập trùng.
            foreach ($billable as $item) {
                $item['line']->increment('invoiced_qty', $item['qty']);
            }

            return $invoice->id;
        });
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
