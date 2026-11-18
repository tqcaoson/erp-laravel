<?php

namespace App\Modules\Manufacturing\Support;

use App\Core\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tra cứu danh mục kho/sản phẩm/đơn vị (module Inventory + Core) bằng query builder.
 * KHÔNG import model của module khác. Bọc try/catch để không lỗi 500 khi bảng
 * products/warehouses chưa được tạo (phát triển song song).
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

    /** Danh sách sản phẩm cho form chọn (id, sku, name). */
    protected function productList(): array
    {
        if (! $this->catalogTableExists('products')) {
            return [];
        }

        try {
            $query = DB::table('products')->select(['id', 'sku', 'name']);

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

    /** Map id => ['sku' => ..., 'name' => ...] để hiển thị tên sản phẩm. */
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
}
