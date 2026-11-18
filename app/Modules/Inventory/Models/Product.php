<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;
use App\Core\Models\Unit;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Sản phẩm — danh mục dùng chung cho Kho, Mua hàng, Bán hàng, Sản xuất. */
class Product extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'sku', 'name', 'uom_id', 'cost_method', 'track_lot', 'track_serial',
        'reorder_min', 'sale_price', 'average_cost', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_lot' => 'boolean',
            'track_serial' => 'boolean',
            'reorder_min' => 'decimal:4',
            'sale_price' => 'decimal:2',
            'average_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** Đơn vị tính chính của sản phẩm. */
    public function uom()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    /** Các đơn vị quy đổi (ví dụ thùng 24 chai). */
    public function units()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    public function quants()
    {
        return $this->hasMany(StockQuant::class);
    }

    public function moves()
    {
        return $this->hasMany(StockMove::class);
    }
}
