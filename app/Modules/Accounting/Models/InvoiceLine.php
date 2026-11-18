<?php

namespace App\Modules\Accounting\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng hóa đơn (hàng hóa/dịch vụ, số lượng, đơn giá, thuế). */
class InvoiceLine extends BaseModel
{
    /** Bảng dòng không có cột company_id nên không lọc theo công ty. */
    protected bool $companyScoped = false;

    protected $fillable = [
        'invoice_id', 'product_id', 'description', 'qty',
        'unit_price', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
