<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Giao dịch ngân hàng (tiền vào / tiền ra, đối chiếu sao kê). */
class BankTransaction extends BaseModel
{
    protected $fillable = [
        'company_id', 'bank_account_id', 'type', 'amount', 'transaction_date',
        'description', 'reference_type', 'reference_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
