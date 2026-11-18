<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;

/** Dòng yêu cầu mua hàng. (Chỉ đọc model Inventory, không sửa dữ liệu kho.) */
class PurchaseRequestLine extends BaseModel
{
    protected $fillable = ['company_id', 'purchase_request_id', 'product_id', 'qty', 'unit_price', 'warehouse_id', 'note'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_price' => 'decimal:2'];
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
