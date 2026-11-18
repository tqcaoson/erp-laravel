<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Định mức nguyên vật liệu (BOM) — hỗ trợ đa cấp (bán thành phẩm cũng có BOM riêng). */
class Bom extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'product_id', 'version', 'is_active', 'note'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Thành phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }

    public function lines()
    {
        return $this->hasMany(BomLine::class);
    }

    public function manufacturingOrders()
    {
        return $this->hasMany(ManufacturingOrder::class);
    }
}
