<?php

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;

/**
 * Luật phân quyền bút toán (theo tên quyền trong RolePermissionSeeder).
 * Lưu ý: coordinator đăng ký tập trung ở giai đoạn tích hợp (không sửa
 * được ModuleServiceProvider); hiện tại route đã chặn bằng middleware
 * 'permission:...', policy này ghi rõ luật để dùng sau.
 */
class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.entry.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->hasPermission('accounting.entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.entry.create');
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $user->hasPermission('accounting.entry.post')
            && $entry->status === JournalEntry::STATUS_DRAFT;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->hasPermission('accounting.entry.reverse')
            && $entry->status === JournalEntry::STATUS_POSTED;
    }
}
