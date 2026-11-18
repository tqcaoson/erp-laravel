<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/** Dòng kiểm kê: số hệ thống, số thực đếm, chênh lệch. */
class StockCountLine extends BaseModel
{
    protected $fillable = ['company_id', 'stock_count_id', 'product_id', 'location_id', 'lot_id', 'system_qty', 'counted_qty', 'difference_qty'];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'difference_qty' => 'decimal:4',
        ];
    }

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}
