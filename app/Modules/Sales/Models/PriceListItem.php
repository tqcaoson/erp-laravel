<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Dòng bảng giá (giá theo sản phẩm + số lượng tối thiểu). */
class PriceListItem extends BaseModel
{
    protected $fillable = ['company_id', 'price_list_id', 'product_id', 'price', 'min_qty'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_qty' => 'decimal:4',
        ];
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    /** Sản phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }
}
