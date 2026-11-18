<?php

namespace App\Core\Models;

use App\Core\Services\CurrentCompany;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Model cơ sở cho toàn bộ ERP.
 *
 * - Tự động lọc theo công ty hiện tại (CompanyScope), trừ khi model
 *   đặt $companyScoped = false (danh mục dùng chung, bảng hệ thống).
 * - Tự động gán company_id, created_by, updated_by khi tạo/cập nhật.
 * - Tự động ghi audit log (trait Auditable).
 */
abstract class BaseModel extends Model
{
    use Auditable;

    /** @var bool Model có bị lọc theo company_id hay không. */
    protected bool $companyScoped = true;

    protected static function booted(): void
    {
        // Chỉ lọc theo công ty khi model thật sự có cột company_id
        // (ví dụ Company, AuditLog không có nên đặt $companyScoped = false).
        if ((new static)->companyScoped) {
            static::addGlobalScope(new CompanyScope);
        }

        static::creating(function (Model $model) {
            if ($model->companyScoped && empty($model->company_id)) {
                $model->company_id = CurrentCompany::id();
            }

            // Chỉ gán người tạo khi bảng thật sự có 2 cột này (phòng trường
            // hợp model dùng chung bảng pivot hoặc bảng hệ thống không có
            // cột created_by/updated_by — tránh lỗi SQL khi insert).
            if (auth()->check() && static::tableHasColumn($model->getTable(), 'created_by')) {
                $model->created_by ??= auth()->id();
            }

            if (auth()->check() && static::tableHasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by ??= auth()->id();
            }
        });

        static::updating(function (Model $model) {
            if (auth()->check() && static::tableHasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Kiểm tra bảng có cột hay không (kết quả cache theo tiến trình để
     * không truy vấn schema mỗi lần tạo/cập nhật model).
     */
    protected static function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];

        if (isset($cache[$table][$column])) {
            return $cache[$table][$column];
        }

        try {
            $cache[$table][$column] = \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            // Bảng chưa tồn tại (vd đang chạy migration) → coi như không có cột.
            $cache[$table][$column] = false;
        }

        return $cache[$table][$column];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    /**
     * Bỏ qua scope công ty cho một truy vấn cụ thể.
     */
    public function scopeWithoutCompanyScope($query)
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }
}
