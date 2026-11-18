<?php

namespace App\Modules\Accounting\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Chuẩn liệt kê API theo hợp đồng mục 6:
 * phân trang page/per_page (mặc định 15, tối đa 100),
 * lọc filter[status]/filter[type], sắp xếp sort=-created_at.
 */
trait HandlesApiListing
{
    protected function applyApiListing(Request $request, Builder $query, array $sortable = ['created_at']): Builder
    {
        // Chỉ cho lọc theo các trường cho phép (tránh lộ cột nhạy cảm như company_id).
        $allowedFilters = ['status', 'type'];

        foreach ($request->input('filter', []) as $field => $value) {
            if ($value !== null && $value !== '' && in_array($field, $allowedFilters, true)) {
                $query->where($field, $value);
            }
        }

        $sort = $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    protected function apiPerPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
