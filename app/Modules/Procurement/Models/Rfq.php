<?php

namespace App\Modules\Procurement\Models;

use App\Core\Models\BaseModel;

/** Yêu cầu báo giá (RFQ) gửi nhà cung cấp. */
class Rfq extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'supplier_id', 'status', 'deadline', 'note'];

    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines()
    {
        return $this->hasMany(RfqLine::class);
    }
}
