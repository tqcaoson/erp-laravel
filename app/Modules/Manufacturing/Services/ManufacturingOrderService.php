<?php

namespace App\Modules\Manufacturing\Services;

use App\Core\Contracts\PurchaseRequestCreatorInterface;
use App\Core\Contracts\StockServiceInterface;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Models\ProductionLog;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ lệnh sản xuất: xác nhận, bắt đầu (kiểm tra nguyên liệu theo BOM
 * đa cấp, thiếu thì gợi ý mua rồi dừng), hoàn thành (xuất nguyên liệu, nhập
 * thành phẩm, ghi nhật ký).
 */
class ManufacturingOrderService
{
    /** Độ sâu tối đa khi展開 BOM đa cấp (tránh đệ quy vô hạn do BOM vòng). */
    public const MAX_BOM_DEPTH = 5;

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

    /** Lấy service tạo yêu cầu mua gợi ý của Procurement, null khi chưa có binding. */
    protected function purchaseRequestCreator(): ?PurchaseRequestCreatorInterface
    {
        try {
            return app()->bound(PurchaseRequestCreatorInterface::class)
                ? app(PurchaseRequestCreatorInterface::class)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Tạo lệnh sản xuất ở trạng thái nháp. */
    public function create(array $data): ManufacturingOrder
    {
        return ManufacturingOrder::create([
            'code' => $this->numbering->next('manufacturing_order'),
            'product_id' => $data['product_id'],
            'bom_id' => $data['bom_id'] ?? null,
            'routing_id' => $data['routing_id'] ?? null,
            'sales_order_id' => $data['sales_order_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'qty' => (float) $data['qty'],
            'status' => 'draft',
            'planned_start_date' => $data['planned_start_date'] ?? null,
            'planned_end_date' => $data['planned_end_date'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }

    /** Xác nhận lệnh (draft → confirmed). */
    public function confirm(ManufacturingOrder $order): ManufacturingOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Chỉ xác nhận được lệnh sản xuất ở trạng thái nháp.');
        }

        $order->update(['status' => 'confirmed']);

        return $order->fresh();
    }

    /**
     * Bắt đầu sản xuất (confirmed → in_progress).
     *
     * Kiểm tra đủ nguyên liệu theo BOM (đa cấp, sâu tối đa 5). Thiếu nguyên liệu
     * thì tạo PurchaseRequest gợi ý (nếu Procurement đã sẵn sàng) rồi ném
     * DomainException liệt kê nguyên liệu thiếu — KHÔNG bắt đầu sản xuất.
     * Đủ nguyên liệu thì tạo WorkOrder theo routing (nếu có).
     */
    public function start(ManufacturingOrder $order): ManufacturingOrder
    {
        if ($order->status !== 'confirmed') {
            throw new DomainException('Chỉ bắt đầu được lệnh sản xuất đã xác nhận.');
        }

        if (! $order->warehouse_id) {
            throw new DomainException('Lệnh sản xuất chưa chọn kho — không kiểm tra được nguyên liệu.');
        }

        $required = $this->requiredMaterials($order);

        if ($required !== []) {
            $stock = $this->stockService();

            if (! $stock) {
                throw new DomainException('Chưa kết nối được kho hàng (StockService chưa khả dụng), không kiểm tra được nguyên liệu.');
            }

            $missing = [];
            foreach ($required as $productId => $qtyNeeded) {
                $available = $stock->availableQty((int) $productId, (int) $order->warehouse_id);
                if ($available < $qtyNeeded) {
                    $missing[(int) $productId] = $qtyNeeded - $available;
                }
            }

            if ($missing !== []) {
                // Gợi ý mua phần thiếu qua Procurement (nếu đã tích hợp).
                $creator = $this->purchaseRequestCreator();
                if ($creator) {
                    $creator->createSuggestion(
                        (int) $order->company_id,
                        collect($missing)->map(fn ($qty, $productId) => [
                            'product_id' => $productId,
                            'qty' => $qty,
                            'warehouse_id' => (int) $order->warehouse_id,
                            'note' => "Thiếu nguyên liệu cho lệnh sản xuất {$order->code}.",
                        ])->values()->all(),
                        "Thiếu nguyên liệu cho lệnh sản xuất {$order->code}",
                    );
                }

                $names = $this->productNames(array_keys($missing));
                $details = collect($missing)->map(
                    fn ($qty, $productId) => ($names[$productId] ?? "Sản phẩm #{$productId}")." (thiếu {$qty})"
                )->implode(', ');

                throw new DomainException('Thiếu nguyên liệu, chưa bắt đầu sản xuất được: '.$details.'. Đã gửi gợi ý mua hàng.');
            }
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'in_progress']);

            // Tạo công đoạn theo routing (nếu lệnh có routing).
            if ($order->routing_id) {
                $order->loadMissing('routing.operations');
                foreach ($order->routing->operations as $operation) {
                    $order->workOrders()->create([
                        'code' => $this->numbering->next('work_order'),
                        'work_center_id' => $operation->work_center_id,
                        'operation_name' => $operation->operation_name,
                        'sequence' => $operation->sequence,
                        'status' => 'pending',
                        'planned_minutes' => $operation->standard_minutes,
                    ]);
                }
            }
        });

        return $order->fresh(['workOrders']);
    }

    /**
     * Ghi nhận tiến độ từng phần: ghi nhật ký, xuất kho nguyên liệu cho
     * đợt này, nhập kho thành phẩm của đợt này, cộng produced_qty.
     * KHÔNG đổi trạng thái lệnh (vẫn in_progress) và KHÔNG đóng công đoạn.
     *
     * @return array{order: ManufacturingOrder, unit_cost: float, warnings: list<string>}
     */
    public function logProgress(ManufacturingOrder $order, float $qtyProduced, float $qtyScrap = 0): array
    {
        return $this->recordProduction($order, $qtyProduced, $qtyScrap, false);
    }

    /**
     * Hoàn thành sản xuất (lần chốt cuối): ghi nhật ký, xuất kho nguyên liệu,
     * nhập kho thành phẩm, đóng lệnh và đóng các công đoạn còn dở.
     *
     * Nguyên liệu chỉ xuất cho số lượng của ĐỢT NÀY (qtyProduced + qtyScrap
     * truyền vào), không tính lại từ đầu — phần nguyên liệu của các lần
     * logProgress trước đã xuất rồi nên không xuất trùng. Ví dụ: đã ghi
     * tiến độ 40 cái, lần chốt này truyền 60 cái thì chỉ xuất nguyên liệu
     * cho 60 cái. produced_qty lũy kế chỉ dùng để hiển thị tiến độ.
     *
     * @return array{order: ManufacturingOrder, unit_cost: float, warnings: list<string>}
     */
    public function complete(ManufacturingOrder $order, float $qtyProduced, float $qtyScrap = 0): array
    {
        return $this->recordProduction($order, $qtyProduced, $qtyScrap, true);
    }

    /**
     * Ghi nhận 1 đợt sản lượng (dùng chung cho logProgress và complete).
     * $close = true thì chốt lệnh (status completed + đóng công đoạn dở).
     *
     * @return array{order: ManufacturingOrder, unit_cost: float, warnings: list<string>}
     */
    protected function recordProduction(ManufacturingOrder $order, float $qtyProduced, float $qtyScrap, bool $close): array
    {
        if ($order->status !== 'in_progress') {
            throw new DomainException('Chỉ ghi nhận tiến độ / hoàn thành được lệnh sản xuất đang thực hiện.');
        }

        if ($qtyProduced <= 0) {
            throw new DomainException('Số lượng thành phẩm phải lớn hơn 0.');
        }

        if ($qtyScrap < 0) {
            throw new DomainException('Số lượng phế phẩm không được âm.');
        }

        if (! $order->warehouse_id) {
            throw new DomainException('Lệnh sản xuất chưa chọn kho — không nhập kho thành phẩm được.');
        }

        // Nguyên liệu tiêu hao tính theo tổng sản lượng của ĐỢT NÀY
        // (thành phẩm + phế phẩm), không tính lại phần đã ghi trước đó.
        $consumed = $this->requiredMaterials($order, $qtyProduced + $qtyScrap);
        $materialCost = 0.0;
        foreach ($consumed as $productId => $qty) {
            $materialCost += $qty * $this->averageCost((int) $productId);
        }
        $unitCost = $materialCost / $qtyProduced;

        $stock = $this->stockService();
        $warnings = [];

        if (! $stock && $consumed !== []) {
            $warnings[] = 'Chưa kết nối được kho hàng nên chưa xuất nguyên liệu / nhập thành phẩm thật — cần đối soát tồn sau.';
        }

        // Kiểm tra tồn trước khi xuất: không bao giờ để tồn âm lặng.
        // StockService::issue() cũng tự ném DomainException khi thiếu tồn,
        // nhưng kiểm tra trước ở đây cho thông báo rõ ràng theo lệnh sản xuất
        // và tránh xuất dở dang (transaction sẽ rollback, nhưng báo lỗi sớm
        // thì người dùng biết ngay nguyên liệu nào thiếu bao nhiêu).
        if ($stock && $consumed !== []) {
            $names = $this->productNames(array_keys($consumed));
            foreach ($consumed as $productId => $qty) {
                $available = $stock->availableQty((int) $productId, (int) $order->warehouse_id);
                if ($available < $qty) {
                    $name = $names[$productId] ?? ("Sản phẩm #{$productId}");
                    throw new DomainException(
                        "Không đủ tồn kho để ghi nhận đợt này: {$name} tại kho chỉ còn "
                        .number_format($available, 2, ',', '.').', cần xuất '.number_format($qty, 2, ',', '.').'.'
                    );
                }
            }
        }

        DB::transaction(function () use ($order, $qtyProduced, $qtyScrap, $consumed, $unitCost, $stock, $close) {
            // Xuất kho nguyên liệu đã tiêu hao cho đợt này.
            if ($stock) {
                foreach ($consumed as $productId => $qty) {
                    $stock->issue(
                        (int) $productId,
                        (int) $order->warehouse_id,
                        null,
                        (float) $qty,
                        ManufacturingOrder::class,
                        $order->id,
                    );
                }

                // Nhập kho thành phẩm của đợt này với giá vốn vừa tính.
                $stock->receive(
                    (int) $order->product_id,
                    (int) $order->warehouse_id,
                    null,
                    $qtyProduced,
                    $unitCost,
                    ManufacturingOrder::class,
                    $order->id,
                );
            }

            ProductionLog::create([
                'manufacturing_order_id' => $order->id,
                'product_id' => $order->product_id,
                'qty_produced' => $qtyProduced,
                'qty_scrap' => $qtyScrap,
                'logged_at' => now(),
            ]);

            $updates = ['produced_qty' => (float) $order->produced_qty + $qtyProduced];

            if ($close) {
                $updates['status'] = 'completed';
            }

            $order->update($updates);

            if ($close) {
                // Đóng các công đoạn còn dở.
                $order->workOrders()->whereIn('status', ['pending', 'in_progress'])->update(['status' => 'completed']);
            }
        });

        return [
            'order' => $order->fresh(['workOrders', 'productionLogs']),
            'unit_cost' => round($unitCost, 4),
            'warnings' => $warnings,
        ];
    }

    /** Hủy lệnh (chỉ khi chưa bắt đầu sản xuất). */
    public function cancel(ManufacturingOrder $order): ManufacturingOrder
    {
        if (! in_array($order->status, ['draft', 'confirmed'], true)) {
            throw new DomainException('Không hủy được lệnh sản xuất đã bắt đầu hoặc đã hoàn thành.');
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh();
    }

    /**
     * Nhu cầu nguyên liệu cho lệnh:展開 BOM đa cấp theo số lượng cần sản xuất.
     * Trả về [product_id => qty]. Lệnh không có BOM thì trả về mảng rỗng.
     */
    public function requiredMaterials(ManufacturingOrder $order, ?float $qty = null): array
    {
        $qty ??= (float) $order->qty;

        $bom = $order->bom_id
            ? Bom::with('lines')->find($order->bom_id)
            : Bom::with('lines')->where('product_id', $order->product_id)->where('is_active', true)->first();

        if (! $bom) {
            return [];
        }

        return $this->explodeBom($bom, $qty);
    }

    /**
     * Triển khai BOM đệ quy: nếu nguyên liệu thành phần cũng có BOM đang hoạt
     * động thì展开 tiếp thành nguyên liệu cấp dưới. Giới hạn độ sâu 5.
     *
     * @return array<int, float> [product_id => qty]
     */
    public function explodeBom(Bom $bom, float $qty, int $depth = 0): array
    {
        $result = [];

        foreach ($bom->lines as $line) {
            // Tính hao hụt: scrap_rate là phần trăm (vd 5 = hao 5%).
            $needed = (float) $line->qty * $qty * (1 + (float) $line->scrap_rate / 100);
            $componentId = (int) $line->component_product_id;

            $subBom = null;
            if ($depth < self::MAX_BOM_DEPTH) {
                $subBom = Bom::with('lines')
                    ->where('product_id', $componentId)
                    ->where('is_active', true)
                    ->first();
            }

            if ($subBom) {
                foreach ($this->explodeBom($subBom, $needed, $depth + 1) as $subId => $subQty) {
                    $result[$subId] = ($result[$subId] ?? 0) + $subQty;
                }
            } else {
                $result[$componentId] = ($result[$componentId] ?? 0) + $needed;
            }
        }

        return $result;
    }

    /** Giá vốn bình quân của 1 sản phẩm: qua StockService nếu có, không thì đọc bảng products. */
    protected function averageCost(int $productId): float
    {
        $stock = $this->stockService();

        if ($stock) {
            return $stock->averageCost($productId);
        }

        return (float) DB::table('products')->where('id', $productId)->value('average_cost');
    }

    /** Tên sản phẩm theo id (đọc bằng query builder, không import model Product). */
    protected function productNames(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('products')->whereIn('id', $productIds)->pluck('name', 'id')->all();
    }
}
