<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/** Vị trí trong kho: khu (zone) → kệ (shelf) → ô (bin), cây qua parent_id. */
class Location extends BaseModel
{
    protected $fillable = ['company_id', 'warehouse_id', 'parent_id', 'code', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function quants()
    {
        return $this->hasMany(StockQuant::class);
    }
}
