<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/**
 * Dòng chi tiết phiếu lương (từng khoản thu nhập/khấu trừ).
 * Bảng này KHÔNG có cột company_id nên tắt scope công ty.
 */
class PayslipLine extends BaseModel
{
    protected bool $companyScoped = false;

    protected $fillable = ['payslip_id', 'component_code', 'label', 'amount', 'type'];

    protected $casts = ['amount' => 'decimal:2'];

    /**
     * Ghi đè booted() của BaseModel: bảng này không có cột company_id /
     * created_by / updated_by nên không được gán tự động khi tạo
     * (gán sẽ gây lỗi "no such column" khi insert). Audit log vẫn chạy
     * qua trait Auditable (bootTraits chạy độc lập với booted()).
     */
    protected static function booted(): void
    {
        // Cố ý không gọi parent::booted().
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class);
    }
}
