<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;

/** Yêu cầu mua hàng. Luồng: draft → pending_approval → approved → converted (thành PO). */
class PurchaseRequest extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'requested_by', 'status', 'total_amount', 'note'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
