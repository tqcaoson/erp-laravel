<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hóa đơn bán (sale) / mua (purchase).
 * customer_id / supplier_id KHÔNG có FK (bảng đó chạy sau) nên chỉ lưu id.
 */
class Invoice extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'type', 'customer_id', 'supplier_id',
        'source_type', 'source_id', 'invoice_date', 'due_date',
        'subtotal', 'tax_amount', 'total_amount', 'status', 'posted_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /** Luôn kèm số đã thu/trả và số còn lại khi serialize sang Inertia/API. */
    protected $appends = ['paid_amount', 'remaining_amount'];

    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Cache tổng đã thanh toán trong vòng đời model (tránh truy vấn lặp). */
    protected ?float $paidAmountCache = null;

    /** Tổng tiền đã thanh toán (chỉ tính phiếu đã ghi sổ). */
    public function getPaidAmountAttribute(): float
    {
        return $this->paidAmountCache ??= (float) $this->payments()
            ->where('status', Payment::STATUS_POSTED)
            ->sum('amount');
    }

    /** Số tiền còn phải thu/trả. */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->paid_amount);
    }
}
