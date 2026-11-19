<?php

namespace App\Modules\Sales\Services;

use App\Core\Contracts\StockServiceInterface;
use App\Core\Models\ApprovalLog;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use App\Modules\Sales\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ đơn bán hàng: tạo đơn, xác nhận (kèm kiểm tra hạn mức công nợ
 * và tồn khả dụng), duyệt đơn vượt hạn mức, hủy đơn.
 */
class SalesOrderService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
    ) {}

    /**
     * Lấy StockService của Inventory qua interface. Trả về null khi module
     * Inventory chưa đăng ký binding — khi đó các kiểm tra tồn sẽ bỏ qua
     * kèm cảnh báo thay vì làm hỏng luồng bán hàng.
     */
    protected function stockService(): ?StockServiceInterface
    {
        try {
            return app()->bound(StockServiceInterface::class)
                ? app(StockServiceInterface::class)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Tạo đơn bán ở trạng thái nháp kèm các dòng, tự tính tiền. */
    public function create(array $data): SalesOrder
    {
        $totals = DocumentTotals::compute($data['lines'] ?? [], (float) ($data['discount_amount'] ?? 0));

        return DB::transaction(function () use ($data, $totals) {
            $order = SalesOrder::create([
                'code' => $this->numbering->next('sales_order'),
                'customer_id' => $data['customer_id'],
                'quotation_id' => $data['quotation_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'note' => $data['note'] ?? null,
            ]);

            foreach ($totals['lines'] as $line) {
                $order->lines()->create([
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_rate' => $line['discount_rate'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order->fresh(['lines']);
        });
    }

    /**
     * Sửa đơn bán — CHỈ khi còn ở trạng thái nháp. Đơn đã xác nhận trở đi
     * (đã phát sinh phiếu giao / công nợ) tuyệt đối không được sửa nội dung.
     *
     * Đồng bộ dòng theo id: dòng có id thì cập nhật, dòng không id thì thêm
     * mới, dòng cũ vắng mặt trong request thì xóa. Tổng tiền tính lại qua
     * DocumentTotals. Toàn bộ chạy trong 1 transaction.
     */
    public function update(SalesOrder $order, array $data): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Chỉ sửa được đơn bán ở trạng thái nháp.');
        }

        $totals = DocumentTotals::compute($data['lines'] ?? [], (float) ($data['discount_amount'] ?? 0));

        return DB::transaction(function () use ($order, $data, $totals) {
            $order->update([
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'] ?? $order->order_date,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'note' => $data['note'] ?? null,
            ]);

            $keptIds = [];

            foreach ($totals['lines'] as $line) {
                $attributes = [
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_rate' => $line['discount_rate'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $line['line_total'],
                ];

                if (! empty($line['id'])) {
                    $existing = $order->lines()->whereKey((int) $line['id'])->first();

                    if (! $existing) {
                        throw new DomainException("Dòng #{$line['id']} không thuộc đơn bán này.");
                    }

                    $existing->update($attributes);
                    $keptIds[] = $existing->id;
                } else {
                    $keptIds[] = $order->lines()->create($attributes)->id;
                }
            }

            // Dòng cũ vắng mặt trong request thì xóa.
            $order->lines()->whereNotIn('id', $keptIds)->delete();

            return $order->fresh(['lines']);
        });
    }

    /**
     * Lập hóa đơn nháp từ phần đã giao chưa lập hóa đơn của đơn bán.
     *
     * Mỗi dòng hóa đơn lấy (delivered_qty − invoiced_qty) × đơn giá của
     * dòng đơn, giữ nguyên thuế suất và chiết khấu dòng (chiết khấu gộp
     * vào đơn giá vì dòng hóa đơn không có cột chiết khấu riêng).
     * Hóa đơn gắn source_type/source_id về đơn bán để khi ghi sổ, listener
     * MarkSalesOrderInvoiced đánh dấu đơn thành "đã lập hóa đơn".
     *
     * @return int id hóa đơn nháp vừa tạo
     *
     * @throws DomainException khi đơn chưa giao hàng, đã lập hóa đơn hết
     *                         phần đã giao, hoặc module Kế toán chưa khả dụng.
     */
    public function createInvoiceFromDelivered(SalesOrder $order): int
    {
        if (in_array($order->status, ['draft', 'pending_approval', 'cancelled', 'closed'], true)) {
            throw new DomainException('Đơn bán chưa giao hàng nên chưa lập được hóa đơn.');
        }

        $order->loadMissing(['lines', 'customer']);

        $billable = [];
        foreach ($order->lines as $line) {
            $qty = $line->billableQty();

            if ($qty > 0) {
                $billable[] = ['line' => $line, 'qty' => $qty];
            }
        }

        if ($billable === []) {
            // Phân biệt: chưa giao gì cả vs đã giao nhưng đã lập hóa đơn hết.
            $deliveredTotal = $order->lines->sum(fn ($line) => (float) $line->delivered_qty);

            if ($deliveredTotal <= 0) {
                throw new DomainException('Đơn bán chưa giao hàng nên chưa lập được hóa đơn.');
            }

            throw new DomainException('Đơn bán không còn phần đã giao nào chưa lập hóa đơn.');
        }

        if (! class_exists(\App\Modules\Accounting\Services\InvoiceService::class)) {
            throw new DomainException('Module Kế toán chưa khả dụng nên chưa lập được hóa đơn.');
        }

        // Tên sản phẩm cho diễn giải dòng hóa đơn (đọc bằng query builder,
        // không import model của Inventory — theo quy tắc phụ thuộc module).
        $productNames = DB::table('products')
            ->whereIn('id', collect($billable)->map(fn ($item) => (int) $item['line']->product_id)->all())
            ->pluck('name', 'id');

        // Chiết khấu cấp đơn (discount_amount) cũng phải trừ vào hóa đơn:
        // dòng hóa đơn không có cột chiết khấu nên gộp theo tỷ lệ vào đơn giá
        // (cùng cách xử lý chiết khấu dòng). Hệ số = 1 − chiết khấu / subtotal
        // của cả đơn; lập hóa đơn từng phần thì mỗi hóa đơn trừ đúng phần
        // chiết khấu tương ứng với phần đã giao của nó, cộng đủ các lần
        // lập sẽ bằng đúng chiết khấu của đơn. Thuế GTGT tính trên giá
        // đã trừ chiết khấu (đúng căn cứ tính thuế của hóa đơn).
        $orderSubtotal = $order->lines->sum(
            fn ($l) => (float) $l->qty * (float) $l->unit_price * (1 - (float) $l->discount_rate / 100)
        );
        $orderDiscount = (float) $order->discount_amount;
        $orderDiscountFactor = 1.0;
        if ($orderDiscount > 0 && $orderSubtotal > 0) {
            $orderDiscountFactor = 1 - min($orderDiscount, $orderSubtotal) / $orderSubtotal;
        }

        $invoiceLines = [];
        foreach ($billable as $item) {
            $line = $item['line'];
            $discountRate = (float) $line->discount_rate;
            // Gộp chiết khấu dòng + chiết khấu cấp đơn vào đơn giá
            // (dòng hóa đơn không có cột chiết khấu).
            $unitPrice = round((float) $line->unit_price * (1 - $discountRate / 100) * $orderDiscountFactor, 2);
            $name = $productNames[(int) $line->product_id] ?? ('Sản phẩm #'.$line->product_id);
            $description = $discountRate > 0
                ? "{$name} — đã trừ chiết khấu {$discountRate}%"
                : $name;
            if ($orderDiscountFactor < 1) {
                $description .= ' — đã trừ chiết khấu đơn bán';
            }

            $invoiceLines[] = [
                'product_id' => (int) $line->product_id,
                'description' => $description,
                'qty' => $item['qty'],
                'unit_price' => $unitPrice,
                'tax_rate' => (float) $line->tax_rate,
            ];
        }

        $dueDate = null;
        $termDays = (int) ($order->customer->payment_term_days ?? 0);
        if ($termDays > 0) {
            $dueDate = now()->addDays($termDays)->toDateString();
        }

        return DB::transaction(function () use ($order, $billable, $invoiceLines, $dueDate) {
            $invoice = app(\App\Modules\Accounting\Services\InvoiceService::class)->create([
                'type' => 'sale',
                'customer_id' => (int) $order->customer_id,
                'source_type' => SalesOrder::class,
                'source_id' => $order->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'lines' => $invoiceLines,
            ], (int) $order->company_id);

            // Ghi nhận phần vừa lập hóa đơn để lần sau không lập trùng.
            foreach ($billable as $item) {
                $item['line']->increment('invoiced_qty', $item['qty']);
            }

            return $invoice->id;
        });
    }

    /**
     * Xác nhận đơn bán (draft → confirmed).
     *
     * (a) Hạn mức công nợ: nếu công nợ hiện tại + tổng đơn vượt credit_limit
     * (và credit_limit > 0) thì KHÔNG từ chối hẳn — chuyển sang pending_approval
     * để kế toán trưởng duyệt (đúng ví dụ mục 8 thiết kế).
     * (b) Tồn khả dụng: thiếu tồn thì vẫn cho xác nhận nhưng trả về cảnh báo
     * (Inventory sẽ giao phần đủ + gợi ý mua phần thiếu).
     *
     * @return array{status: string, needs_approval: bool, warnings: list<string>, order: SalesOrder}
     */
    public function confirm(SalesOrder $order): array
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Chỉ xác nhận được đơn bán ở trạng thái nháp.');
        }

        $order->loadMissing(['lines', 'customer']);
        $warnings = $this->checkStockAvailability($order);

        $customer = $order->customer;
        $creditLimit = (float) $customer->credit_limit;
        $needsApproval = $creditLimit > 0
            && ($customer->currentDebt() + (float) $order->total_amount) > $creditLimit;

        if ($needsApproval) {
            $order->update(['status' => 'pending_approval']);

            return [
                'status' => 'pending_approval',
                'needs_approval' => true,
                'warnings' => $warnings,
                'order' => $order->fresh(['lines', 'customer']),
                'message' => 'Đơn vượt hạn mức công nợ của khách hàng nên chuyển sang chờ duyệt.',
            ];
        }

        $this->markConfirmed($order);

        return [
            'status' => 'confirmed',
            'needs_approval' => false,
            'warnings' => $warnings,
            'order' => $order->fresh(['lines', 'customer']),
            'message' => 'Đã xác nhận đơn bán.',
        ];
    }

    /**
     * Duyệt đơn đang chờ duyệt (pending_approval → confirmed) + phát event.
     *
     * Maker-checker: người tạo đơn không được duyệt đơn của chính mình.
     * Chỉ chặn khi biết cả người tạo và người duyệt (cả hai non-null);
     * gọi trực tiếp không đăng nhập thì bỏ qua kiểm tra này.
     * Ghi ApprovalLog như PurchaseOrderService::approve() (chỉ ghi khi
     * có người đăng nhập vì cột approver_id bắt buộc).
     */
    public function approve(SalesOrder $order): array
    {
        if ($order->status !== 'pending_approval') {
            throw new DomainException('Chỉ duyệt được đơn bán ở trạng thái chờ duyệt.');
        }

        $approverId = auth()->id();

        if ($approverId !== null && $order->created_by !== null && (int) $approverId === (int) $order->created_by) {
            throw new DomainException('Người tạo đơn không được duyệt đơn của chính mình.');
        }

        $order->loadMissing(['lines', 'customer']);
        $warnings = $this->checkStockAvailability($order);

        if ($approverId !== null) {
            ApprovalLog::create([
                'company_id' => $order->company_id,
                'approval_rule_id' => null,
                'approvable_type' => SalesOrder::class,
                'approvable_id' => $order->id,
                'approver_id' => $approverId,
                'level' => 1,
                'decision' => 'approved',
                'note' => null,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->markConfirmed($order);

        return [
            'status' => 'confirmed',
            'needs_approval' => false,
            'warnings' => $warnings,
            'order' => $order->fresh(['lines', 'customer']),
            'message' => 'Đã duyệt và xác nhận đơn bán.',
        ];
    }

    /** Hủy đơn (chỉ khi chưa giao hàng). */
    public function cancel(SalesOrder $order): SalesOrder
    {
        if (! in_array($order->status, ['draft', 'pending_approval', 'confirmed'], true)) {
            throw new DomainException('Không hủy được đơn bán đã bắt đầu giao hàng hoặc đã lập hóa đơn.');
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh();
    }

    /** Kiểm tra tồn khả dụng từng dòng, trả về danh sách cảnh báo (rỗng nếu đủ tồn). */
    public function checkStockAvailability(SalesOrder $order): array
    {
        $stock = $this->stockService();

        if (! $stock) {
            return ['Chưa kết nối được kho hàng nên chưa kiểm tra tồn khả dụng.'];
        }

        $warnings = [];

        foreach ($order->lines as $line) {
            $available = $stock->availableQty((int) $line->product_id, (int) $order->warehouse_id);

            if ($available < (float) $line->qty) {
                $warnings[] = "Sản phẩm #{$line->product_id} chỉ còn {$available} khả dụng, thiếu "
                    .((float) $line->qty - $available).' so với đơn hàng.';
            }
        }

        return $warnings;
    }

    /** Chuyển đơn sang confirmed rồi phát event SalesOrderConfirmed SAU KHI commit. */
    protected function markConfirmed(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'confirmed']);
        });

        // Dispatch sau commit để listener không đọc phải dữ liệu chưa lưu xong.
        SalesOrderConfirmed::dispatch(
            $order->id,
            (int) $order->company_id,
            (int) $order->customer_id,
            (int) $order->warehouse_id,
            $order->lines->map(fn ($line) => [
                'sales_order_line_id' => $line->id,
                'product_id' => (int) $line->product_id,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
            ])->all(),
        );
    }
}
