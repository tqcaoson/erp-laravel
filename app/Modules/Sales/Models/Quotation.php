<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;

/** Báo giá. */
class Quotation extends BaseModel
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'sent' => 'Đã gửi',
        'accepted' => 'Đã chấp nhận',
        'rejected' => 'Từ chối',
        'expired' => 'Hết hạn',
    ];

    protected $fillable = [
        'company_id', 'code', 'customer_id', 'opportunity_id', 'status', 'valid_until',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function lines()
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }
}
