<?php

namespace App\Modules\Manufacturing\Models;

use App\Core\Models\BaseModel;

/** Quy trình sản xuất (danh sách công đoạn theo thứ tự). */
class Routing extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'name', 'product_id'];

    /** Sản phẩm áp dụng quy trình (module Inventory) — chỉ đọc qua tên class dạng chuỗi. */
    public function product()
    {
        return $this->belongsTo('App\Modules\Inventory\Models\Product');
    }

    public function operations()
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('sequence');
    }

    public function manufacturingOrders()
    {
        return $this->hasMany(ManufacturingOrder::class);
    }
}
