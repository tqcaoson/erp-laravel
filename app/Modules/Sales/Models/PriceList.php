<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Bảng giá. */
class PriceList extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(PriceListItem::class);
    }
}
