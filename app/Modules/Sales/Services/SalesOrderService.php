<?php

namespace App\Modules\Sales\Services;

use App\Core\Contracts\StockServiceInterface;
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

    /** Duyệt đơn đang chờ duyệt (pending_approval → confirmed) + phát event. */
    public function approve(SalesOrder $order): array
    {
        if ($order->status !== 'pending_approval') {
            throw new DomainException('Chỉ duyệt được đơn bán ở trạng thái chờ duyệt.');
        }

        $order->loadMissing(['lines', 'customer']);
        $warnings = $this->checkStockAvailability($order);
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
