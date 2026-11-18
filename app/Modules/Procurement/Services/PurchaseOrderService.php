<?php

namespace App\Modules\Procurement\Services;

use App\Core\Models\ApprovalLog;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Đơn mua hàng (PO): tạo, trình duyệt, duyệt, nhận hàng từng phần, hủy, đóng.
 */
class PurchaseOrderService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected PurchaseRequestService $purchaseRequestService,
    ) {}

    /**
     * @param  array{supplier_id: int, purchase_request_id?: int|null, order_date: string, expected_date?: string|null, tax_amount?: float, note?: string|null, lines: array<int, array{product_id: int, qty: float, unit_price: float}>} $data
     */
    public function create(array $data, int $companyId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $companyId) {
            $order = PurchaseOrder::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('purchase_order', $companyId),
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => 0,
                'tax_amount' => (float) ($data['tax_amount'] ?? 0),
                'total_amount' => 0,
                'note' => $data['note'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['lines'] as $line) {
                $lineTotal = (float) $line['qty'] * (float) $line['unit_price'];

                PurchaseOrderLine::create([
                    'company_id' => $companyId,
                    'purchase_order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'received_qty' => 0,
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => round($lineTotal, 2),
                ]);

                $subtotal += $lineTotal;
            }

            $order->update([
                'subtotal' => round($subtotal, 2),
                'total_amount' => round($subtotal + (float) $order->tax_amount, 2),
            ]);

            return $order;
        });
    }

    /** Trình duyệt: draft → pending_approval. */
    public function submit(int $orderId): PurchaseOrder
    {
        $order = PurchaseOrder::query()->findOrFail($orderId);

        if ($order->status !== 'draft') {
            throw new DomainException('Chỉ đơn mua ở trạng thái nháp mới được trình duyệt.');
        }

        $order->update(['status' => 'pending_approval']);

        return $order;
    }

    /** Duyệt PO (ghi ApprovalLog). PR nguồn (nếu có) được đánh dấu converted. */
    public function approve(int $orderId, ?string $note = null): PurchaseOrder
    {
        $order = PurchaseOrder::query()->findOrFail($orderId);

        if (! in_array($order->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Chỉ đơn mua nháp hoặc chờ duyệt mới được duyệt.');
        }

        if (! auth()->check()) {
            throw new DomainException('Cần đăng nhập để duyệt đơn mua hàng.');
        }

        ApprovalLog::create([
            'company_id' => $order->company_id,
            'approval_rule_id' => null,
            'approvable_type' => PurchaseOrder::class,
            'approvable_id' => $order->id,
            'approver_id' => auth()->id(),
            'level' => 1,
            'decision' => 'approved',
            'note' => $note,
            'ip_address' => request()->ip(),
        ]);

        $order->update(['status' => 'approved']);

        if ($order->purchase_request_id) {
            $this->purchaseRequestService->markConverted($order->purchase_request_id);
        }

        return $order;
    }

    /**
     * Ghi nhận đã nhận hàng từng dòng (được gọi từ GoodsReceiptService).
     * Tự chuyển trạng thái: đủ hết → received, chưa đủ → partially_received.
     *
     * @param  array<int, float> $receivedLines [purchase_order_line_id => qty vừa nhận]
     */
    public function receive(int $orderId, array $receivedLines): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId, $receivedLines) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, ['approved', 'partially_received'], true)) {
                throw new DomainException('Chỉ nhận hàng khi đơn mua đã được duyệt.');
            }

            foreach ($receivedLines as $lineId => $qty) {
                $line = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($lineId);

                if ($line->purchase_order_id !== $order->id) {
                    throw new DomainException('Dòng đơn mua không thuộc đơn mua này.');
                }

                if ((float) $line->received_qty + (float) $qty > (float) $line->qty) {
                    throw new DomainException('Số lượng nhận vượt quá số lượng đặt mua.');
                }

                $line->increment('received_qty', (float) $qty);
            }

            $allReceived = $order->lines()->get()->every(
                fn (PurchaseOrderLine $l) => (float) $l->received_qty >= (float) $l->qty
            );

            $order->update(['status' => $allReceived ? 'received' : 'partially_received']);

            return $order;
        });
    }

    /** Hủy PO — chỉ khi chưa nhận hàng dòng nào. */
    public function cancel(int $orderId): PurchaseOrder
    {
        $order = PurchaseOrder::query()->findOrFail($orderId);

        if (! in_array($order->status, ['draft', 'pending_approval', 'approved'], true)) {
            throw new DomainException('Đơn mua ở trạng thái này không thể hủy.');
        }

        if ((float) $order->lines()->sum('received_qty') > 0) {
            throw new DomainException('Không thể hủy đơn mua đã nhận hàng.');
        }

        $order->update(['status' => 'cancelled']);

        return $order;
    }

    /** Đóng PO đã nhận đủ hàng. */
    public function close(int $orderId): PurchaseOrder
    {
        $order = PurchaseOrder::query()->findOrFail($orderId);

        if ($order->status !== 'received') {
            throw new DomainException('Chỉ đơn mua đã nhận đủ hàng mới được đóng.');
        }

        $order->update(['status' => 'closed']);

        return $order;
    }
}
