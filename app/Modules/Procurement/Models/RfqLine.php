<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Product;

/** Dòng báo giá: sản phẩm, số lượng, giá chào của nhà cung cấp. */
class RfqLine extends BaseModel
{
    protected $fillable = ['company_id', 'rfq_id', 'product_id', 'qty', 'quoted_price'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'quoted_price' => 'decimal:2'];
    }

    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
