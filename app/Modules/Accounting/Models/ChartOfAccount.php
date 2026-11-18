<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tài khoản kế toán theo TT200 (cấp 1 là mã 3 số, cấp 2 là mã chi tiết). */
class ChartOfAccount extends BaseModel
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'parent_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Các loại tài khoản (nhóm theo bảng cân đối / kết quả kinh doanh). */
    public const TYPES = [
        'asset' => 'Tài sản',
        'liability' => 'Nợ phải trả',
        'equity' => 'Vốn chủ sở hữu',
        'revenue' => 'Doanh thu',
        'expense' => 'Chi phí',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** Nhãn tiếng Việt của loại tài khoản. */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
