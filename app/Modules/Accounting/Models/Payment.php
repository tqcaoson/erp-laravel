<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phiếu thu (receipt) / phiếu chi (payment). */
class Payment extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'type', 'partner_type', 'partner_id',
        'bank_account_id', 'invoice_id', 'amount', 'payment_date',
        'status', 'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_PAYMENT = 'payment';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
