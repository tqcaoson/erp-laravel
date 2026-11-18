<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kho hàng (mỗi công ty có thể có nhiều kho). */
class Warehouse extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function quants()
    {
        return $this->hasMany(StockQuant::class);
    }

    public function stockCounts()
    {
        return $this->hasMany(StockCount::class);
    }
}
