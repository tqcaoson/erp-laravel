<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Bút toán tổng hợp (chứng từ ghi sổ). */
class JournalEntry extends BaseModel
{
    protected $fillable = [
        'company_id', 'fiscal_period_id', 'code', 'entry_date', 'description',
        'source_type', 'source_id', 'status', 'reversed_by_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /** Bút toán đảo ngược bút toán này (nếu có). */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    /** Bút toán gốc mà bút toán này đảo ngược (khi đây là bút toán đảo). */
    public function reverses(): HasOne
    {
        return $this->hasOne(self::class, 'reversed_by_id');
    }

    /** Tổng phát sinh Nợ của bút toán. */
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    /** Tổng phát sinh Có của bút toán. */
    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    /** Bút toán cân bằng khi tổng Nợ = tổng Có (sai số cho phép 0,01). */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) <= 0.01;
    }
}
