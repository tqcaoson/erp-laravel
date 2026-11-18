<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/** Lô hàng — dùng để theo dõi hạn sử dụng và xuất theo FEFO. */
class Lot extends BaseModel
{
    protected $fillable = ['company_id', 'product_id', 'code', 'expiry_date'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function quants()
    {
        return $this->hasMany(StockQuant::class);
    }
}
