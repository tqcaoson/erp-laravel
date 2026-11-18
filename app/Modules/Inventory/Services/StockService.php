<?php

namespace App\Modules\Inventory\Services;

use App\Core\Contracts\StockServiceInterface;
use App\Modules\Inventory\Events\StockBelowMinimum;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMove;
use App\Modules\Inventory\Models\StockQuant;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Dịch vụ tồn kho — nơi DUY NHẤT được thay đổi tồn.
 *
 * - Mọi thay đổi tồn đi qua stock_moves (append-only) + cập nhật
 *   stock_quants trong CÙNG một transaction.
 * - Xuất kho khóa dòng stock_quants (SELECT ... FOR UPDATE) rồi mới
 *   kiểm tra tồn khả dụng, theo mục 5.4 của thiết kế.
 * - Giá vốn: phương pháp bình quân gia quyền (avg). Sản phẩm cấu hình
 *   cost_method = fifo hiện cũng dùng giá vốn bình quân: giá nhập của
 *   từng lần nhập vẫn được lưu trong stock_moves.unit_cost để sau này
 *   triển khai FIFO đầy đủ (trừ dần theo lô nhập cũ nhất) mà không cần
 *   đổi schema. Xem ghi chú trong receive().
 */
class StockService implements StockServiceInterface
{
    public function availableQty(int $productId, int $warehouseId): float
    {
        return (float) StockQuant::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('COALESCE(SUM(qty - reserved_qty), 0) as total')
            ->value('total');
    }

    public function receive(int $productId, int $warehouseId, ?int $locationId, float $qty, float $unitCost, string $sourceType, int $sourceId, ?int $lotId = null): int
    {
        if ($qty <= 0) {
            throw new DomainException('Số lượng nhập kho phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $locationId, $qty, $unitCost, $sourceType, $sourceId, $lotId) {
            // Khóa dòng sản phẩm để tính giá vốn bình quân không bị race.
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            // Tính lại giá vốn bình quân gia quyền trên TỔNG tồn mọi kho:
            // (tồn cũ × giá cũ + nhập × giá nhập) / (tồn cũ + nhập).
            // Chỉ áp dụng khi cost_method = avg. Với fifo, average_cost được
            // giữ nguyên (chưa triển khai trừ theo lô nhập); giá nhập từng
            // lần vẫn lưu trong stock_moves.unit_cost để đối chiếu sau.
            if ($product->cost_method === 'avg') {
                $oldTotalQty = (float) StockQuant::query()->where('product_id', $productId)->sum('qty');
                $oldAverage = (float) $product->average_cost;
                $newTotalQty = $oldTotalQty + $qty;
                $newAverage = $newTotalQty > 0
                    ? (($oldTotalQty * $oldAverage) + ($qty * $unitCost)) / $newTotalQty
                    : $unitCost;
                $product->forceFill(['average_cost' => round($newAverage, 4)])->save();
            }

            $quant = $this->findOrCreateQuant($product, $warehouseId, $locationId, $lotId);

            $move = StockMove::create([
                'company_id' => $product->company_id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'lot_id' => $lotId,
                'qty' => $qty, // Dương = nhập kho.
                'unit_cost' => $unitCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'moved_at' => now(),
            ]);

            $quant->increment('qty', $qty);

            $this->checkLowStockAfterCommit($product, $warehouseId);

            return $move->id;
        });
    }

    public function issue(int $productId, int $warehouseId, ?int $locationId, float $qty, string $sourceType, int $sourceId, ?int $lotId = null): int
    {
        if ($qty <= 0) {
            throw new DomainException('Số lượng xuất kho phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $locationId, $qty, $sourceType, $sourceId, $lotId) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            // Khóa TOÀN BỘ dòng tồn của sản phẩm tại kho (FOR UPDATE) rồi mới
            // kiểm tra, để 2 giao dịch đồng thời không xuất vượt tồn.
            $quants = StockQuant::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->orderBy('created_at')
                ->get();

            $available = $quants->sum(fn (StockQuant $q) => $q->availableQty());

            if ($available < $qty) {
                throw new DomainException(
                    "Không đủ tồn kho: sản phẩm {$product->name} (SKU {$product->sku}) tại kho chỉ còn "
                    .number_format($available, 2, ',', '.').", cần xuất ".number_format($qty, 2, ',', '.').'.'
                );
            }

            // Trừ tồn: ưu tiên dòng khớp vị trí/lô được chỉ định, sau đó trừ
            // dần theo thứ tự tạo (hàng về trước xuất trước).
            $ordered = $quants->sortByDesc(fn (StockQuant $q) => (int) (
                ($locationId === null || $q->location_id === $locationId)
                && ($lotId === null || $q->lot_id === $lotId)
            ))->values();

            $remaining = $qty;
            foreach ($ordered as $quant) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct = min($quant->availableQty(), $remaining);
                if ($deduct <= 0) {
                    continue;
                }

                $quant->decrement('qty', $deduct);
                $remaining -= $deduct;
            }

            $unitCost = (float) $product->average_cost;

            $move = StockMove::create([
                'company_id' => $product->company_id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'from_location_id' => $locationId,
                'to_location_id' => null,
                'lot_id' => $lotId,
                'qty' => -$qty, // Âm = xuất kho.
                'unit_cost' => $unitCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'moved_at' => now(),
            ]);

            $this->checkLowStockAfterCommit($product, $warehouseId);

            return $move->id;
        });
    }

    public function transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, ?int $fromLocationId, ?int $toLocationId, float $qty, string $sourceType, int $sourceId): int
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new DomainException('Kho nguồn và kho đích phải khác nhau.');
        }

        // Xuất kho nguồn + nhập kho đích trong 1 transaction (transaction lồng
        // nhau của Laravel dùng savepoint nên lỗi ở bước sau sẽ rollback cả 2).
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $fromLocationId, $toLocationId, $qty, $sourceType, $sourceId) {
            $unitCost = $this->averageCost($productId);

            $this->issue($productId, $fromWarehouseId, $fromLocationId, $qty, $sourceType, $sourceId);

            return $this->receive($productId, $toWarehouseId, $toLocationId, $qty, $unitCost, $sourceType, $sourceId);
        });
    }

    public function averageCost(int $productId): float
    {
        return (float) Product::query()->findOrFail($productId)->average_cost;
    }

    /**
     * Tìm dòng tồn khớp (sản phẩm, kho, vị trí, lô), chưa có thì tạo mới với qty = 0.
     * Vị trí/lô null được hiểu là "tồn chung không gắn vị trí/lô cụ thể".
     */
    protected function findOrCreateQuant(Product $product, int $warehouseId, ?int $locationId, ?int $lotId): StockQuant
    {
        $query = StockQuant::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId);

        $locationId === null
            ? $query->whereNull('location_id')
            : $query->where('location_id', $locationId);

        $lotId === null
            ? $query->whereNull('lot_id')
            : $query->where('lot_id', $lotId);

        $quant = $query->lockForUpdate()->first();

        return $quant ?? StockQuant::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'lot_id' => $lotId,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
    }

    /**
     * Sau khi commit: nếu tồn khả dụng xuống dưới reorder_min thì phát event
     * StockBelowMinimum để Procurement gợi ý mua hàng.
     */
    protected function checkLowStockAfterCommit(Product $product, int $warehouseId): void
    {
        $productId = $product->id;
        $companyId = $product->company_id;
        $reorderMin = (float) $product->reorder_min;

        if ($reorderMin <= 0) {
            return;
        }

        DB::afterCommit(function () use ($productId, $companyId, $warehouseId, $reorderMin) {
            $currentQty = $this->availableQty($productId, $warehouseId);

            if ($currentQty < $reorderMin) {
                StockBelowMinimum::dispatch($productId, $companyId, $warehouseId, $currentQty, $reorderMin);
            }
        });
    }
}
