<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Warehouse;

/** Phiếu nhận hàng — đối chiếu số lượng với PO, cho phép nhận từng phần. */
class GoodsReceipt extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'purchase_order_id', 'supplier_id', 'warehouse_id', 'status', 'receipt_date', 'note'];

    protected function casts(): array
    {
        return ['receipt_date' => 'date'];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
