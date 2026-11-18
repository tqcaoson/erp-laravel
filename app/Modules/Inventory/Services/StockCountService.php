<?php

namespace App\Modules\Inventory\Services;

use App\Core\Services\DocumentNumberingService;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockQuant;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Kiểm kê kho: tạo phiếu (chụp số hệ thống), nhập số thực tế,
 * hoàn tất → sinh stock_moves điều chỉnh chênh lệch.
 */
class StockCountService
{
    public function __construct(
        protected StockService $stockService,
        protected DocumentNumberingService $numbering,
    ) {}

    /**
     * Tạo phiếu kiểm kê và chụp system_qty từ stock_quants tại thời điểm tạo.
     * Nếu không chỉ định sản phẩm thì kiểm kê toàn bộ sản phẩm có tồn tại kho.
     *
     * @param  array<int> $productIds
     */
    public function create(int $companyId, int $warehouseId, array $productIds = [], ?string $note = null): StockCount
    {
        return DB::transaction(function () use ($companyId, $warehouseId, $productIds, $note) {
            $count = StockCount::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('stock_count', $companyId),
                'warehouse_id' => $warehouseId,
                'status' => 'draft',
                'note' => $note,
            ]);

            $totals = StockQuant::query()
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            $ids = $productIds !== [] ? $productIds : $totals->keys()->all();

            foreach ($ids as $productId) {
                StockCountLine::create([
                    'company_id' => $companyId,
                    'stock_count_id' => $count->id,
                    'product_id' => $productId,
                    'system_qty' => (float) ($totals[$productId] ?? 0),
                    'counted_qty' => null,
                    'difference_qty' => 0,
                ]);
            }

            return $count;
        });
    }

    /** Bắt đầu kiểm kê: draft → in_progress. */
    public function start(int $countId): StockCount
    {
        $count = StockCount::query()->findOrFail($countId);

        if ($count->status !== 'draft') {
            throw new DomainException('Chỉ phiếu kiểm kê ở trạng thái nháp mới được bắt đầu.');
        }

        $count->update(['status' => 'in_progress']);

        return $count;
    }

    /**
     * Nhập số thực tế cho các dòng kiểm kê.
     *
     * @param  array<int, array{line_id: int, counted_qty: float}> $lines
     */
    public function updateCounted(int $countId, array $lines): StockCount
    {
        $count = StockCount::query()->findOrFail($countId);

        if (! in_array($count->status, ['draft', 'in_progress'], true)) {
            throw new DomainException('Phiếu kiểm kê đã hoàn tất hoặc đã hủy, không thể nhập số liệu.');
        }

        DB::transaction(function () use ($count, $lines) {
            foreach ($lines as $line) {
                $countLine = StockCountLine::query()
                    ->where('stock_count_id', $count->id)
                    ->findOrFail($line['line_id']);

                $counted = (float) $line['counted_qty'];

                $countLine->update([
                    'counted_qty' => $counted,
                    'difference_qty' => $counted - (float) $countLine->system_qty,
                ]);
            }

            if ($count->status === 'draft') {
                $count->update(['status' => 'in_progress']);
            }
        });

        return $count->refresh();
    }

    /**
     * Hoàn tất kiểm kê: sinh stock_moves điều chỉnh chênh lệch
     * (thừa thì nhập, thiếu thì xuất) rồi đóng phiếu.
     * Dòng chưa nhập số thực tế được bỏ qua (không điều chỉnh).
     */
    public function complete(int $countId): StockCount
    {
        return DB::transaction(function () use ($countId) {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);

            if (! in_array($count->status, ['draft', 'in_progress'], true)) {
                throw new DomainException('Chỉ phiếu kiểm kê nháp hoặc đang kiểm mới được hoàn tất.');
            }

            foreach ($count->lines()->lockForUpdate()->get() as $line) {
                if ($line->counted_qty === null) {
                    continue;
                }

                $difference = (float) $line->counted_qty - (float) $line->system_qty;

                if ($difference > 0) {
                    // Thừa hàng: nhập kho điều chỉnh theo giá vốn bình quân hiện tại.
                    $this->stockService->receive(
                        $line->product_id,
                        $count->warehouse_id,
                        $line->location_id,
                        $difference,
                        $this->stockService->averageCost($line->product_id),
                        StockCount::class,
                        $count->id,
                        $line->lot_id,
                    );
                } elseif ($difference < 0) {
                    // Thiếu hàng: xuất kho điều chỉnh (thiếu tồn sẽ ném DomainException
                    // và rollback toàn bộ phiếu — đúng, vì số hệ thống đã sai lệch).
                    $this->stockService->issue(
                        $line->product_id,
                        $count->warehouse_id,
                        $line->location_id,
                        abs($difference),
                        StockCount::class,
                        $count->id,
                        $line->lot_id,
                    );
                }

                $line->update(['difference_qty' => $difference]);
            }

            $count->update(['status' => 'completed', 'counted_at' => now()]);

            return $count;
        });
    }

    /** Hủy phiếu kiểm kê (chỉ khi chưa hoàn tất). */
    public function cancel(int $countId): StockCount
    {
        $count = StockCount::query()->findOrFail($countId);

        if (! in_array($count->status, ['draft', 'in_progress'], true)) {
            throw new DomainException('Chỉ phiếu kiểm kê nháp hoặc đang kiểm mới được hủy.');
        }

        $count->update(['status' => 'cancelled']);

        return $count;
    }
}
