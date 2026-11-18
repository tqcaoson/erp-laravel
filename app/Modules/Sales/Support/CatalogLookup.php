<?php

namespace App\Modules\Sales\Support;

use App\Core\Contracts\StockServiceInterface;
use App\Core\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tra cứu danh mục kho/sản phẩm (module Inventory) bằng query builder.
 *
 * KHÔNG import model của Inventory. Mọi truy vấn đều bọc try/catch và kiểm tra
 * bảng tồn tại, vì khi phát triển song song bảng products/warehouses có thể
 * chưa được tạo — khi đó trả về danh sách rỗng thay vì lỗi 500.
 */
trait CatalogLookup
{
    protected function catalogTableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Danh sách sản phẩm cho form chọn (id, sku, name, sale_price). */
    protected function productList(): array
    {
        if (! $this->catalogTableExists('products')) {
            return [];
        }

        try {
            $query = DB::table('products')->select(['id', 'sku', 'name', 'sale_price']);

            if (CurrentCompany::id()) {
                $query->where('company_id', CurrentCompany::id());
            }

            return $query->orderBy('name')->get()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Danh sách kho cho form chọn (id, code, name). */
    protected function warehouseList(): array
    {
        if (! $this->catalogTableExists('warehouses')) {
            return [];
        }

        try {
            $query = DB::table('warehouses')->select(['id', 'code', 'name']);

            if (CurrentCompany::id()) {
                $query->where('company_id', CurrentCompany::id());
            }

            return $query->orderBy('name')->get()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Map id => ['sku' => ..., 'name' => ...] để hiển thị tên sản phẩm trên dòng chứng từ. */
    protected function productMap(array $ids): array
    {
        if ($ids === [] || ! $this->catalogTableExists('products')) {
            return [];
        }

        try {
            return DB::table('products')->whereIn('id', $ids)->get(['id', 'sku', 'name'])
                ->mapWithKeys(fn ($row) => [$row->id => ['sku' => $row->sku, 'name' => $row->name]])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Map id => tên kho. */
    protected function warehouseMap(array $ids): array
    {
        if ($ids === [] || ! $this->catalogTableExists('warehouses')) {
            return [];
        }

        try {
            return DB::table('warehouses')->whereIn('id', $ids)->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Tồn khả dụng [product_id => qty] tại 1 kho; rỗng khi StockService chưa khả dụng. */
    protected function stockMap(int $warehouseId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        try {
            if (! app()->bound(StockServiceInterface::class)) {
                return [];
            }

            $stock = app(StockServiceInterface::class);
            $map = [];
            foreach ($productIds as $productId) {
                $map[(int) $productId] = $stock->availableQty((int) $productId, $warehouseId);
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }
}
