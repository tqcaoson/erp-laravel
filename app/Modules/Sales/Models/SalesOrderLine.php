<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Dòng đơn bán hàng. */
class SalesOrderLine extends BaseModel
{
    protected $fillable = [
        'company_id', 'sales_order_id', 'product_id', 'qty', 'delivered_qty',
        'unit_price', 'discount_rate', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'delivered_qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** Sản phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }

    /** Số lượng còn phải giao. */
    public function remainingQty(): float
    {
        return max(0, (float) $this->qty - (float) $this->delivered_qty);
    }
}
