<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kỳ kế toán theo tháng. Kỳ đã khóa (closed) thì không được ghi sổ thêm. */
class FiscalPeriod extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'start_date', 'end_date', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
