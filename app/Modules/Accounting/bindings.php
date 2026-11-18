<?php

use App\Core\Contracts\JournalPostingInterface;
use App\Modules\Accounting\Services\JournalEntryService;

/**
 * Đăng ký implementation cho contract liên module.
 * ModuleServiceProvider tự nạp file này (không cần sửa provider).
 */
return [
    JournalPostingInterface::class => JournalEntryService::class,
];
