<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;
use App\Core\Models\Unit;

/** Dòng BOM: một nguyên liệu/bán thành phẩm cần cho 1 đơn vị thành phẩm. */
class BomLine extends BaseModel
{
    protected $fillable = [
        'company_id', 'bom_id', 'component_product_id', 'qty', 'unit_id', 'scrap_rate',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'scrap_rate' => 'decimal:4',
        ];
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    /** Nguyên liệu thành phần (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function componentProduct()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product', 'component_product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
