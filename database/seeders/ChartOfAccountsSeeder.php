<?php

namespace Database\Seeders;

use App\Core\Models\Company;
use App\Core\Services\CurrentCompany;
use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Hệ thống tài khoản theo TT200 rút gọn (~35 tài khoản).
 * Bao gồm đầy đủ các mã dùng trong bút toán tự động:
 * 111, 112, 131, 133, 156, 331, 3331, 3335, 334, 338,
 * 511, 521, 622, 632, 642, 711, 811.
 *
 * Idempotent: firstOrCreate theo (code, company_id), chạy lại không trùng.
 * Gieo cho mọi công ty đã có trong hệ thống.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** [mã, tên, loại, mã tài khoản cha (null = cấp 1)] */
    public const ACCOUNTS = [
        // Tài sản
        ['111', 'Tiền mặt', 'asset', null],
        ['112', 'Tiền gửi ngân hàng', 'asset', null],
        ['121', 'Chứng khoán kinh doanh', 'asset', null],
        ['128', 'Đầu tư nắm giữ đến ngày đáo hạn', 'asset', null],
        ['131', 'Phải thu của khách hàng', 'asset', null],
        ['1311', 'Phải thu khách hàng trong nước', 'asset', '131'],
        ['133', 'Thuế GTGT được khấu trừ', 'asset', null],
        ['141', 'Tạm ứng', 'asset', null],
        ['152', 'Nguyên liệu, vật liệu', 'asset', null],
        ['153', 'Công cụ, dụng cụ', 'asset', null],
        ['154', 'Chi phí sản xuất, kinh doanh dở dang', 'asset', null],
        ['155', 'Thành phẩm', 'asset', null],
        ['156', 'Hàng hóa', 'asset', null],
        ['211', 'Tài sản cố định hữu hình', 'asset', null],
        ['214', 'Hao mòn tài sản cố định', 'asset', null],
        // Nợ phải trả
        ['331', 'Phải trả cho người bán', 'liability', null],
        ['3311', 'Phải trả người bán trong nước', 'liability', '331'],
        ['333', 'Thuế và các khoản phải nộp Nhà nước', 'liability', null],
        ['3331', 'Thuế giá trị gia tăng phải nộp', 'liability', '333'],
        ['3335', 'Thuế thu nhập cá nhân', 'liability', '333'],
        ['334', 'Phải trả người lao động', 'liability', null],
        ['338', 'Phải trả, phải nộp khác', 'liability', null],
        ['341', 'Vay và nợ thuê tài chính', 'liability', null],
        // Vốn chủ sở hữu
        ['411', 'Vốn đầu tư của chủ sở hữu', 'equity', null],
        ['421', 'Lợi nhuận sau thuế chưa phân phối', 'equity', null],
        // Doanh thu / thu nhập
        ['511', 'Doanh thu bán hàng và cung cấp dịch vụ', 'revenue', null],
        ['5111', 'Doanh thu bán hàng hóa', 'revenue', '511'],
        ['515', 'Doanh thu hoạt động tài chính', 'revenue', null],
        ['521', 'Các khoản giảm trừ doanh thu', 'revenue', null],
        ['711', 'Thu nhập khác', 'revenue', null],
        // Chi phí
        ['622', 'Chi phí nhân công trực tiếp', 'expense', null],
        ['632', 'Giá vốn hàng bán', 'expense', null],
        ['641', 'Chi phí bán hàng', 'expense', null],
        ['642', 'Chi phí quản lý doanh nghiệp', 'expense', null],
        ['811', 'Chi phí khác', 'expense', null],
    ];

    public function run(): void
    {
        // Giữ lại công ty đang chọn trước đó (nếu có) để khôi phục sau khi gieo.
        $previousCompanyId = CurrentCompany::id();

        foreach (Company::all() as $company) {
            CurrentCompany::setId($company->id);

            // Gieo cấp 1 trước để tài khoản chi tiết tra được parent_id.
            $byCode = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $parentCode]) {
                $byCode[$code] = ChartOfAccount::firstOrCreate(
                    ['code' => $code, 'company_id' => $company->id],
                    [
                        'name' => $name,
                        'type' => $type,
                        'parent_id' => $parentCode ? ($byCode[$parentCode]->id ?? null) : null,
                        'is_active' => true,
                    ],
                );
            }
        }

        CurrentCompany::setId($previousCompanyId);
    }
}
