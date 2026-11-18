<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Product;

/** Dòng phiếu nhận hàng. */
class GoodsReceiptLine extends BaseModel
{
    protected $fillable = ['company_id', 'goods_receipt_id', 'purchase_order_line_id', 'product_id', 'qty', 'unit_cost', 'lot_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}
