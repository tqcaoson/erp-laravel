<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Khách hàng. */
class Customer extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'tax_code', 'phone', 'email', 'address',
        'credit_limit', 'payment_term_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Công nợ hiện tại — CÁCH TÍNH ĐƠN GIẢN TRONG PHẠM VI SALES.
     *
     * = tổng tiền các đơn bán đã xác nhận trở đi (confirmed, partially_delivered,
     * delivered, invoiced). Chưa trừ số tiền khách đã thanh toán, vì dữ liệu
     * thanh toán thuộc module Accounting (hóa đơn/thu tiền). Đủ để demo kiểm tra
     * hạn mức khi xác nhận đơn; con số công nợ chính xác lấy từ Accounting.
     */
    public function currentDebt(): float
    {
        return (float) $this->salesOrders()
            ->whereIn('status', ['confirmed', 'partially_delivered', 'delivered', 'invoiced'])
            ->sum('total_amount');
    }

    /**
     * Hạn mức công nợ còn lại. Trả về null khi không giới hạn (credit_limit <= 0).
     */
    public function remainingCredit(): ?float
    {
        if ((float) $this->credit_limit <= 0) {
            return null;
        }

        return (float) $this->credit_limit - $this->currentDebt();
    }
}
