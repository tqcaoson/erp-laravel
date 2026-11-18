<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/**
 * Tồn tổng hợp theo (sản phẩm, kho, vị trí, lô).
 * Luôn được cập nhật trong cùng transaction với stock_moves.
 * Tồn khả dụng = qty - reserved_qty.
 */
class StockQuant extends BaseModel
{
    protected $fillable = ['company_id', 'product_id', 'warehouse_id', 'location_id', 'lot_id', 'qty', 'reserved_qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'reserved_qty' => 'decimal:4'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    /** Tồn khả dụng của dòng tồn này. */
    public function availableQty(): float
    {
        return (float) $this->qty - (float) $this->reserved_qty;
    }
}
