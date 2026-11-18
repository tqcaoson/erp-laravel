<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/** Phiếu kiểm kê kho. */
class StockCount extends BaseModel
{
    protected $fillable = ['company_id', 'code', 'warehouse_id', 'status', 'note', 'counted_at'];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(StockCountLine::class);
    }
}
