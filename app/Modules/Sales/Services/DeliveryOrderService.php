<?php

namespace App\Modules\Sales\Services;

use App\Core\Contracts\DeliveryOrderCreatorInterface;
use App\Core\Contracts\StockServiceInterface;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Inventory\Events\DeliveryOrderValidated;
use App\Modules\Sales\Models\DeliveryOrder;
use App\Modules\Sales\Models\DeliveryOrderLine;
use App\Modules\Sales\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ phiếu giao hàng.
 *
 * CÁCH validate() HOẠT ĐỘNG (đã tích hợp với Inventory):
 * - Ưu tiên gọi App\Modules\Inventory\Services\DeliveryValidationService::validate()
 *   (đúng mẫu mà Inventory đã ghi trong docblock của service đó): service kho xuất
 *   kho thật qua StockService, trả về các dòng kèm giá vốn bình quân, và tự phát
 *   event DeliveryOrderValidated. Sales chỉ việc lưu unit_cost + chuyển trạng thái
 *   phiếu sang validated. Cả hai bước nằm trong cùng 1 transaction ngoài nên event
 *   (đăng ký bằng DB::afterCommit bên Inventory) chỉ phát sau khi commit xong.
 * - Dự phòng: nếu class đó chưa tồn tại (chạy Sales độc lập), validate() tự xuất
 *   kho qua StockServiceInterface + đọc average_cost từ bảng products bằng query
 *   builder rồi tự phát event DeliveryOrderValidated. Controller/route/event không
 *   cần đổi trong cả hai trường hợp.
 */
class DeliveryOrderService implements DeliveryOrderCreatorInterface
{
    public function __construct(
        protected DocumentNumberingService $numbering,
    ) {}

    /** Lấy StockService của Inventory qua interface, null khi chưa có binding. */
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

    /**
     * Tạo phiếu giao hàng ở trạng thái nháp từ đơn bán (implement hợp đồng
     * DeliveryOrderCreatorInterface — Inventory gọi khi nhận SalesOrderConfirmed).
     *
     * @param  array<int, array{sales_order_line_id: int, product_id: int, qty: float, warehouse_id: int}> $lines
     */
    public function createForSalesOrder(int $salesOrderId, array $lines): int
    {
        $order = SalesOrder::findOrFail($salesOrderId);

        $deliveryOrder = DB::transaction(function () use ($order, $lines) {
            $warehouseId = (int) ($lines[0]['warehouse_id'] ?? $order->warehouse_id);

            $deliveryOrder = DeliveryOrder::create([
                'company_id' => $order->company_id,
                'code' => $this->numbering->next('delivery_order', (int) $order->company_id),
                'sales_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'status' => 'draft',
                'delivery_date' => now()->toDateString(),
            ]);

            foreach ($lines as $line) {
                $deliveryOrder->lines()->create([
                    'sales_order_line_id' => $line['sales_order_line_id'],
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_cost' => 0,
                ]);
            }

            return $deliveryOrder;
        });

        return $deliveryOrder->id;
    }

    /**
     * Tạo phiếu giao hàng nháp cho phần còn thiếu của đơn bán (giao nốt
     * sau khi đã giao một phần, vd lần giao đầu thiếu tồn).
     *
     * Với mỗi dòng: phần thiếu = còn phải giao (remainingQty) TRỪ đi lượng
     * đã nằm trong các phiếu giao NHÁP chưa xác nhận của cùng đơn (chống
     * giao vượt), rồi lấy min với tồn khả dụng tại kho — cùng logic với
     * listener tạo phiếu giao lúc xác nhận đơn.
     *
     * @return array{order: DeliveryOrder, warnings: list<string>}
     *
     * @throws DomainException khi đơn chưa xác nhận, đã giao đủ, phần thiếu
     *                         đã nằm hết trong phiếu nháp, hoặc hết tồn.
     */
    public function createForRemaining(SalesOrder $order): array
    {
        if (! in_array($order->status, ['confirmed', 'partially_delivered', 'delivered'], true)) {
            throw new DomainException('Chỉ giao phần còn lại cho đơn bán đã xác nhận.');
        }

        $order->loadMissing('lines');

        // Lượng đã nằm trong các phiếu giao nháp của đơn (theo từng dòng đơn).
        $draftIds = DeliveryOrder::query()
            ->where('sales_order_id', $order->id)
            ->where('status', 'draft')
            ->pluck('id');

        $reservedByLine = $draftIds->isEmpty()
            ? collect()
            : DeliveryOrderLine::query()
                ->whereIn('delivery_order_id', $draftIds)
                ->selectRaw('sales_order_line_id, SUM(qty) as total')
                ->groupBy('sales_order_line_id')
                ->pluck('total', 'sales_order_line_id');

        $stock = $this->stockService();
        $warnings = [];

        if (! $stock) {
            $warnings[] = 'Chưa kết nối được kho hàng nên chưa kiểm tra tồn khả dụng.';
        }

        // Tồn khả dụng theo sản phẩm (trừ dần khi phân bổ cho từng dòng,
        // để 2 dòng cùng sản phẩm không cùng tính trên một lượng tồn).
        $availableByProduct = [];
        $deliveryLines = [];
        $hasRemaining = false;
        $allCoveredByDraft = true;

        foreach ($order->lines as $line) {
            $remaining = $line->remainingQty();

            if ($remaining <= 0) {
                continue;
            }

            $hasRemaining = true;

            $reserved = (float) ($reservedByLine[$line->id] ?? 0);
            $uncovered = $remaining - $reserved;

            // Phần thiếu đã nằm hết trong phiếu nháp thì không giao thêm.
            if ($uncovered <= 0) {
                continue;
            }

            $allCoveredByDraft = false;

            if ($stock) {
                $productId = (int) $line->product_id;

                if (! array_key_exists($productId, $availableByProduct)) {
                    $availableByProduct[$productId] = $stock->availableQty($productId, (int) $order->warehouse_id);
                }

                $deliverQty = min($availableByProduct[$productId], $uncovered);
                $availableByProduct[$productId] -= $deliverQty;

                if ($deliverQty < $uncovered) {
                    $warnings[] = "Sản phẩm #{$productId} chỉ còn đủ tồn để giao {$deliverQty} trên {$uncovered} còn thiếu.";
                }
            } else {
                $deliverQty = $uncovered;
            }

            if ($deliverQty > 0) {
                $deliveryLines[] = [
                    'sales_order_line_id' => $line->id,
                    'product_id' => (int) $line->product_id,
                    'qty' => $deliverQty,
                    'warehouse_id' => (int) $order->warehouse_id,
                ];
            }
        }

        if ($deliveryLines === []) {
            if (! $hasRemaining) {
                throw new DomainException('Đơn bán đã giao đủ toàn bộ các dòng.');
            }

            if ($allCoveredByDraft) {
                throw new DomainException('Phần còn thiếu đã nằm trong phiếu giao nháp chưa xác nhận — hãy xác nhận phiếu đó trước khi tạo phiếu mới.');
            }

            throw new DomainException('Không còn tồn khả dụng để giao phần còn lại của đơn bán.');
        }

        $deliveryOrderId = $this->createForSalesOrder($order->id, $deliveryLines);

        return [
            'order' => DeliveryOrder::with('lines')->findOrFail($deliveryOrderId),
            'warnings' => $warnings,
        ];
    }

    /**
     * Xác nhận giao hàng (draft → validated): xuất kho thật từng dòng, ghi nhận
     * giá vốn (average_cost) rồi phát event DeliveryOrderValidated để Sales cập
     * nhật trạng thái đơn bán và Accounting ghi bút toán giá vốn.
     *
     * Nếu kho chưa tích hợp (cả DeliveryValidationService lẫn StockService đều
     * chưa có) thì vẫn chuyển trạng thái + phát event, kèm cảnh báo — tồn kho
     * sẽ được đối soát lại sau.
     *
     * @return array{status: string, warnings: list<string>, order: DeliveryOrder}
     */
    public function validate(DeliveryOrder $deliveryOrder): array
    {
        if ($deliveryOrder->status !== 'draft') {
            throw new DomainException('Chỉ xác nhận được phiếu giao hàng ở trạng thái nháp.');
        }

        $deliveryOrder->loadMissing('lines');

        // Đường chính: nhờ Inventory xuất kho + phát event (xem docblock đầu file).
        if (class_exists(\App\Modules\Inventory\Services\DeliveryValidationService::class)) {
            $plainLines = $deliveryOrder->lines->map(fn ($line) => [
                'product_id' => (int) $line->product_id,
                'qty' => (float) $line->qty,
            ])->all();

            DB::transaction(function () use ($deliveryOrder, $plainLines) {
                // Ném DomainException khi thiếu tồn → transaction rollback, phiếu giữ nguyên draft.
                // Event DeliveryOrderValidated do service kho phát qua DB::afterCommit nên
                // chỉ chạy sau khi transaction ngoài này commit xong.
                $enrichedLines = app(\App\Modules\Inventory\Services\DeliveryValidationService::class)
                    ->validate(
                        deliveryOrderId: $deliveryOrder->id,
                        salesOrderId: (int) $deliveryOrder->sales_order_id,
                        companyId: (int) $deliveryOrder->company_id,
                        warehouseId: (int) $deliveryOrder->warehouse_id,
                        lines: $plainLines,
                    );

                $costByProduct = collect($enrichedLines)->mapWithKeys(
                    fn ($line) => [(int) $line['product_id'] => (float) $line['unit_cost']]
                )->all();

                foreach ($deliveryOrder->lines as $line) {
                    $line->update(['unit_cost' => $costByProduct[(int) $line->product_id] ?? 0]);
                }

                $deliveryOrder->update(['status' => 'validated']);
            });

            return [
                'status' => 'validated',
                'warnings' => [],
                'order' => $deliveryOrder->fresh(['lines']),
            ];
        }

        // Đường dự phòng: Inventory chưa có DeliveryValidationService — tự xuất kho
        // qua StockServiceInterface + tự phát event.
        return $this->validateDirectly($deliveryOrder);
    }

    /**
     * Xác nhận giao hàng không qua DeliveryValidationService: xuất kho qua
     * StockServiceInterface (nếu có), đọc average_cost từ bảng products bằng
     * query builder (KHÔNG import model Product), rồi tự phát event.
     */
    protected function validateDirectly(DeliveryOrder $deliveryOrder): array
    {
        $stock = $this->stockService();
        $warnings = [];

        if (! $stock) {
            $warnings[] = 'Chưa kết nối được kho hàng nên chưa trừ tồn thật — phiếu vẫn được xác nhận, cần đối soát tồn sau.';
        }

        $eventLines = [];

        DB::transaction(function () use ($deliveryOrder, $stock, &$eventLines) {
            foreach ($deliveryOrder->lines as $line) {
                // Giá vốn bình quân: đọc trực tiếp bảng products.
                $unitCost = (float) DB::table('products')
                    ->where('id', $line->product_id)
                    ->value('average_cost');

                if ($stock) {
                    // Xuất kho thật; thiếu tồn thì StockService ném DomainException, transaction rollback.
                    $stock->issue(
                        (int) $line->product_id,
                        (int) $deliveryOrder->warehouse_id,
                        null,
                        (float) $line->qty,
                        DeliveryOrder::class,
                        $deliveryOrder->id,
                    );
                }

                $line->update(['unit_cost' => $unitCost]);

                $eventLines[] = [
                    'product_id' => (int) $line->product_id,
                    'qty' => (float) $line->qty,
                    'unit_cost' => $unitCost,
                ];
            }

            $deliveryOrder->update(['status' => 'validated']);
        });

        // Phát event SAU KHI commit.
        DeliveryOrderValidated::dispatch(
            $deliveryOrder->id,
            (int) $deliveryOrder->company_id,
            (int) $deliveryOrder->sales_order_id,
            (int) $deliveryOrder->warehouse_id,
            $eventLines,
        );

        return [
            'status' => 'validated',
            'warnings' => $warnings,
            'order' => $deliveryOrder->fresh(['lines']),
        ];
    }

    /** Hủy phiếu giao (chỉ khi còn nháp). */
    public function cancel(DeliveryOrder $deliveryOrder): DeliveryOrder
    {
        if ($deliveryOrder->status !== 'draft') {
            throw new DomainException('Chỉ hủy được phiếu giao hàng ở trạng thái nháp.');
        }

        $deliveryOrder->update(['status' => 'cancelled']);

        return $deliveryOrder->fresh();
    }
}
