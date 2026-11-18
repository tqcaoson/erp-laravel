<?php

namespace App\Core\Support;

use App\Core\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope lọc dữ liệu theo công ty đang chọn (đa công ty).
 *
 * Mọi model kế thừa BaseModel đều tự động chỉ thấy dữ liệu của công ty
 * hiện tại. Khi chưa chọn công ty (console, seeder, job chạy nền chưa
 * set công ty) thì scope không lọc để tránh mất dữ liệu.
 * Dùng ->withoutGlobalScope(CompanyScope::class) khi cần đọc xuyên công ty.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = CurrentCompany::id();

        if ($companyId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
