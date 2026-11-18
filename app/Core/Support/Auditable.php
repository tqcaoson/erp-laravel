<?php

namespace App\Core\Support;

use App\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait ghi audit log cho mọi thay đổi trên model.
 *
 * Ghi lại: người thực hiện, hành động (created/updated/deleted),
 * giá trị cũ và mới (JSON). Log không được sửa/xóa (không có route nào
 * cho phép, model AuditLog không dùng trait này để tránh đệ quy).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAuditLog('created', [], $model->getAttributes()));

        static::updated(function (Model $model) {
            $model->writeAuditLog('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(fn (Model $model) => $model->writeAuditLog('deleted', $model->getOriginal(), []));
    }

    protected function writeAuditLog(string $action, array $oldValues, array $newValues): void
    {
        // Chỉ ghi những trường thay đổi thật sự, bỏ qua timestamp nội bộ.
        $ignored = ['created_at', 'updated_at', 'deleted_at'];

        AuditLog::query()->create([
            'company_id' => $this->getAttribute('company_id'),
            'user_id' => auth()->id(),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'old_values' => array_diff_key($oldValues, array_flip($ignored)) ?: null,
            'new_values' => array_diff_key($newValues, array_flip($ignored)) ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
