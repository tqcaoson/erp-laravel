<?php

namespace App\Core\Models;

/** Tiền tệ (VND, USD...) kèm tỷ giá so với tiền tệ cơ sở của công ty. */
class Currency extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'symbol', 'exchange_rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
