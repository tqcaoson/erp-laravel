<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Người liên hệ của khách hàng. */
class Contact extends BaseModel
{
    protected $fillable = [
        'company_id', 'customer_id', 'name', 'position', 'phone', 'email', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
