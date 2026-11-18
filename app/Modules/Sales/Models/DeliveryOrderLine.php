<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Dòng phiếu giao hàng. */
class DeliveryOrderLine extends BaseModel
{
    protected $fillable = [
        'company_id', 'delivery_order_id', 'sales_order_line_id', 'product_id', 'qty', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderLine()
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /** Sản phẩm (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }
}
