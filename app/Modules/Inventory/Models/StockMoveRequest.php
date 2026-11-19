<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\BaseModel;

/**
 * Phiếu kho tay chờ duyệt.
 * Luồng: thủ kho tạo phiếu (pending) → người có quyền duyệt
 * (inventory.stock.confirm) duyệt thì tồn mới đổi, hoặc từ chối.
 * Lưu ý: tên class trùng với FormRequest cùng module
 * (App\Modules\Inventory\Requests\StockMoveRequest) — khi dùng chung
 * trong một file phải import kèm alias.
 */
class StockMoveRequest extends BaseModel
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const STATUS_LABELS = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Đã từ chối',
    ];

    public const TYPE_LABELS = [
        'receive' => 'Nhập kho',
        'issue' => 'Xuất kho',
        'transfer' => 'Chuyển kho',
    ];

    protected $fillable = [
        'company_id', 'type', 'product_id', 'warehouse_id', 'location_id',
        'to_warehouse_id', 'to_location_id', 'lot_id', 'qty', 'unit_cost',
        'status', 'requested_by', 'approved_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
