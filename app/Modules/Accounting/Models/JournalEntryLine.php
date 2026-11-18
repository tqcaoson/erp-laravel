<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng bút toán. Một dòng chỉ có Nợ hoặc Có (ràng buộc CHECK ở DB),
 * số tiền không âm.
 */
class JournalEntryLine extends BaseModel
{
    /** Bảng dòng không có cột company_id nên không lọc theo công ty. */
    protected bool $companyScoped = false;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'partner_type', 'partner_id',
        'debit', 'credit', 'description',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
