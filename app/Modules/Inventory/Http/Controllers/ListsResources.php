<?php

namespace App\Modules\Inventory\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Dùng chung cho controller Inventory: phân trang, lọc, sắp xếp theo chuẩn API mục 9.1. */
trait ListsResources
{
    /** Số bản ghi mỗi trang: mặc định 15, tối đa 100. */
    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->input('per_page', 15)));
    }

    /**
     * Sắp xếp theo tham số sort (vd sort=-created_at). Chỉ cho phép cột trong danh sách.
     *
     * @param  array<int, string> $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default = '-created_at'): Builder
    {
        $sort = (string) $request->input('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = ltrim($default, '-');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        return $query->orderBy($column, $direction);
    }

    /** Lọc theo filter[field]=value cho các cột cho phép. */
    protected function applyFilters(Builder $query, Request $request, array $allowed): Builder
    {
        $filters = (array) $request->input('filter', []);

        foreach ($allowed as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '' && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }
}
