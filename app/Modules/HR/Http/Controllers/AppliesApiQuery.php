<?php

namespace App\Modules\HR\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Chuẩn API mục 9.1 thiết kế: phân trang page/per_page (mặc định 15,
 * tối đa 100), lọc filter[...], sắp xếp sort=-created_at.
 */
trait AppliesApiQuery
{
    protected function applyApiQuery(Request $request, Builder $query, array $filterable, array $sortable): Builder
    {
        foreach ($request->input('filter', []) as $field => $value) {
            if ($value === null || $value === '' || ! in_array($field, $filterable, true)) {
                continue;
            }

            $query->where($field, $value);
        }

        $sort = (string) $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
