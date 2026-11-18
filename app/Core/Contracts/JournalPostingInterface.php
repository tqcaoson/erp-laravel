<?php

namespace App\Core\Contracts;

/**
 * Hợp đồng ghi bút toán kế toán từ các module nghiệp vụ khác.
 * Implementation: App\Modules\Accounting\Services\JournalEntryService.
 * Tài khoản được tham chiếu bằng MÃ tài khoản (code) để module khác
 * không cần biết id nội bộ của hệ thống tài khoản.
 */
interface JournalPostingInterface
{
    /**
     * Tạo và ghi sổ (post) một bút toán. Tự kiểm tra cân bằng debit = credit
     * và kỳ kế toán chưa khóa; vi phạm sẽ ném \DomainException.
     *
     * @param  array{
     *     entry_date: string,
     *     description: string,
     *     source_type: string|null,
     *     source_id: int|null,
     *     lines: array<int, array{
     *         account_code: string,
     *         debit: float,
     *         credit: float,
     *         partner_type?: string|null,
     *         partner_id?: int|null,
     *         description?: string|null,
     *     }>,
     * } $data
     * @return int id của JournalEntry vừa ghi sổ
     */
    public function post(array $data): int;
}
