<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Nhà cung cấp. */
class Supplier extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'tax_code', 'phone', 'email', 'address', 'payment_term_days', 'is_active'];

    protected function casts(): array
    {
        return ['payment_term_days' => 'integer', 'is_active' => 'boolean'];
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function rfqs()
    {
        return $this->hasMany(Rfq::class);
    }
}
