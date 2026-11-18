<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tài khoản ngân hàng của công ty. */
class BankAccount extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'name', 'bank_name', 'account_number',
        'currency', 'opening_balance', 'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
