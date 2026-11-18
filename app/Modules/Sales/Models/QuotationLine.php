<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Dòng báo giá. */
class QuotationLine extends BaseModel
{
    protected $fillable = [
        'company_id', 'quotation_id', 'product_id', 'qty',
        'unit_price', 'discount_rate', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Sản phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi để không import model module khác. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }
}
