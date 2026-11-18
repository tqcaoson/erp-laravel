<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/**
 * Sổ kho — bảng append-only: mọi thay đổi tồn đều ghi 1 dòng tại đây,
 * không bao giờ sửa/xóa dòng đã ghi. Quy ước qty: dương = nhập, âm = xuất.
 */
class StockMove extends BaseModel
{
    protected $fillable = [
        'company_id', 'product_id', 'from_location_id', 'to_location_id', 'lot_id',
        'qty', 'unit_cost', 'source_type', 'source_id', 'moved_at', 'warehouse_id',
    ];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_cost' => 'decimal:4', 'moved_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}
