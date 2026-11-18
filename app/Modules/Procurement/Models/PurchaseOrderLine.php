<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Product;

/** Dòng đơn mua hàng. */
class PurchaseOrderLine extends BaseModel
{
    protected $fillable = ['company_id', 'purchase_order_id', 'product_id', 'qty', 'received_qty', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'received_qty' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Số lượng còn thiếu (chưa nhận). */
    public function remainingQty(): float
    {
        return (float) $this->qty - (float) $this->received_qty;
    }
}
