<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;
use App\Core\Models\Unit;

/** Đơn vị quy đổi của sản phẩm (ví dụ 1 thùng = 24 chai). */
class ProductUnit extends BaseModel
{
    protected $fillable = ['company_id', 'product_id', 'unit_id', 'conversion_factor', 'is_default'];

    protected function casts(): array
    {
        return ['conversion_factor' => 'decimal:4', 'is_default' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
