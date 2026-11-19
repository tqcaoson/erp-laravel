<?php

namespace Database\Seeders;

use App\Core\Models\Company;
use App\Core\Models\Unit;
use App\Core\Services\DocumentNumberingService;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Position;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomLine;
use App\Modules\Manufacturing\Models\ManufacturingOrder;
use App\Modules\Manufacturing\Models\Routing;
use App\Modules\Manufacturing\Models\RoutingOperation;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationLine;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu demo cho công ty XT Mechanical (theo đúng ví dụ mục 8 của thiết kế):
 * 2 kho HN/HCM, 10 sản phẩm, khách "Công ty Minh Long" (hạn mức 50tr),
 * 2 nhà cung cấp, tồn đầu kỳ, 1 báo giá + 1 đơn bán mẫu ở trạng thái nháp.
 * Idempotent: chạy lại không tạo trùng (firstOrCreate + kiểm tra tồn trước
 * khi nhập kho đầu kỳ).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrFail();
        $companyId = $company->id;
        $numbering = app(DocumentNumberingService::class);

        $units = Unit::query()->whereNull('company_id')->pluck('id', 'code');

        // ---- Kho + vị trí ----
        $hn = Warehouse::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KHO-HN'],
            ['name' => 'Kho Hà Nội', 'address' => 'KCN Thăng Long, Đông Anh, Hà Nội'],
        );
        $hcm = Warehouse::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KHO-HCM'],
            ['name' => 'Kho TP. Hồ Chí Minh', 'address' => 'KCN Tân Bình, TP. Hồ Chí Minh'],
        );

        foreach ([$hn, $hcm] as $warehouse) {
            Location::firstOrCreate(
                ['company_id' => $companyId, 'warehouse_id' => $warehouse->id, 'code' => $warehouse->code.'-A01'],
                ['name' => 'Khu A — Kệ 01', 'type' => 'shelf'],
            );
        }

        // ---- Sản phẩm (giá bán theo ví dụ mục 8: thùng nước ép 420.000đ) ----
        $products = [
            ['NEP-CAM-THUNG', 'Nước ép cam thùng 24 chai', 'thung', 420000, 300000, 20],
            ['NEP-TAO-THUNG', 'Nước ép táo thùng 24 chai', 'thung', 380000, 270000, 20],
            ['NEP-NHO-THUNG', 'Nước ép nho thùng 24 chai', 'thung', 450000, 320000, 20],
            ['TRA-XANH-CHAI', 'Trà xanh 500ml', 'chai', 12000, 8000, 100],
            ['CAFE-HOP', 'Cà phê hòa tan hộp 20 gói', 'hop', 85000, 60000, 30],
            ['SUA-TUOI-LIT', 'Sữa tươi tiệt trùng 1L', 'lit', 32000, 23000, 50],
            ['BANH-QUY-HOP', 'Bánh quy bơ hộp 500g', 'hop', 65000, 45000, 30],
            ['MI-GOI-THUNG', 'Mì gói thùng 30 gói', 'thung', 135000, 95000, 20],
            ['GAO-THOM-GOI', 'Gạo thơm túi 5kg', 'goi', 95000, 70000, 30],
            ['DAU-AN-CHAI', 'Dầu ăn thực vật 1L', 'chai', 48000, 35000, 50],
        ];

        $bySku = [];
        foreach ($products as [$sku, $name, $unitCode, $salePrice, $cost, $reorderMin]) {
            $bySku[$sku] = Product::firstOrCreate(
                ['company_id' => $companyId, 'sku' => $sku],
                [
                    'name' => $name,
                    'uom_id' => $units[$unitCode],
                    'cost_method' => 'avg',
                    'reorder_min' => $reorderMin,
                    'sale_price' => $salePrice,
                    'average_cost' => $cost,
                ],
            );
        }

        // ---- Tồn đầu kỳ (qua StockService để có stock_moves + stock_quants đúng) ----
        $stock = app(StockService::class);
        $opening = [
            // [sku, warehouse, qty, unit_cost] — số lượng đều trên reorder_min
            // để không kích hoạt cảnh báo tồn thấp khi seed.
            ['NEP-CAM-THUNG', $hn, 80, 300000], // đúng ví dụ mục 8: HN có 80 thùng
            ['NEP-TAO-THUNG', $hn, 120, 270000],
            ['NEP-NHO-THUNG', $hn, 60, 320000],
            ['TRA-XANH-CHAI', $hn, 500, 8000],
            ['CAFE-HOP', $hn, 150, 60000],
            ['SUA-TUOI-LIT', $hn, 300, 23000],
            ['BANH-QUY-HOP', $hn, 120, 45000],
            ['MI-GOI-THUNG', $hn, 200, 95000],
            ['NEP-CAM-THUNG', $hcm, 50, 300000],
            ['NEP-TAO-THUNG', $hcm, 40, 270000],
            ['GAO-THOM-GOI', $hcm, 100, 70000],
            ['DAU-AN-CHAI', $hcm, 200, 35000],
        ];

        foreach ($opening as [$sku, $warehouse, $qty, $cost]) {
            $product = $bySku[$sku];
            $exists = \App\Modules\Inventory\Models\StockQuant::query()
                ->where('company_id', $companyId)
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->exists();

            if (! $exists) {
                $stock->receive($product->id, $warehouse->id, null, $qty, $cost, self::class, 1);
            }
        }

        // ---- Khách hàng ----
        $minhLong = Customer::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KH-001'],
            [
                'name' => 'Công ty Minh Long', 'tax_code' => '0302345678',
                'phone' => '028 3822 1111', 'email' => 'mua-hang@minhlong.local',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'credit_limit' => 50000000, 'payment_term_days' => 30,
            ],
        );
        Customer::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KH-002'],
            [
                'name' => 'Siêu thị Co.op Food', 'tax_code' => '0303456789',
                'phone' => '028 3833 2222', 'address' => 'Quận 3, TP. Hồ Chí Minh',
                'credit_limit' => 100000000, 'payment_term_days' => 45,
            ],
        );
        Customer::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KH-003'],
            [
                'name' => 'Cửa hàng tạp hóa Ngọc Anh', 'phone' => '024 3666 3333',
                'address' => 'Quận Đống Đa, Hà Nội',
                'credit_limit' => 10000000, 'payment_term_days' => 15,
            ],
        );
        Customer::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KH-004'],
            [
                'name' => 'Công ty TNHH Phúc Khang', 'tax_code' => '0104567890',
                'phone' => '024 3777 4444', 'address' => 'Quận Cầu Giấy, Hà Nội',
                'credit_limit' => 30000000, 'payment_term_days' => 30,
            ],
        );

        // ---- Nhà cung cấp ----
        Supplier::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'NCC-001'],
            [
                'name' => 'Công ty CP Thực phẩm Đồng Nai', 'tax_code' => '3601234567',
                'phone' => '0251 3888 5555', 'email' => 'sales@dongnai-food.local',
                'address' => 'KCN Biên Hòa, Đồng Nai', 'payment_term_days' => 30,
            ],
        );
        Supplier::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'NCC-002'],
            [
                'name' => 'Công ty TNHH Nước giải khát Sài Gòn', 'tax_code' => '0305678901',
                'phone' => '028 3999 6666', 'address' => 'Quận 7, TP. Hồ Chí Minh',
                'payment_term_days' => 45,
            ],
        );

        // ---- Báo giá mẫu (nháp): 80 thùng nước ép táo cho Minh Long ----
        if (! Quotation::query()->where('company_id', $companyId)->exists()) {
            $quotation = Quotation::create([
                'company_id' => $companyId,
                'code' => $numbering->next('quotation', $companyId),
                'customer_id' => $minhLong->id,
                'status' => 'draft',
                'valid_until' => now()->addDays(15)->toDateString(),
                'subtotal' => 30400000,
                'discount_amount' => 0,
                'tax_amount' => 3040000,
                'total_amount' => 33440000,
            ]);

            QuotationLine::create([
                'company_id' => $companyId,
                'quotation_id' => $quotation->id,
                'product_id' => $bySku['NEP-TAO-THUNG']->id,
                'qty' => 80,
                'unit_price' => 380000,
                'discount_rate' => 0,
                'tax_rate' => 10,
                'line_total' => 30400000,
            ]);
        }

        // ---- Đơn bán mẫu (nháp): đúng ví dụ mục 8 — 100 thùng nước ép cam ----
        if (! SalesOrder::query()->where('company_id', $companyId)->exists()) {
            $order = SalesOrder::create([
                'company_id' => $companyId,
                'code' => $numbering->next('sales_order', $companyId),
                'customer_id' => $minhLong->id,
                'warehouse_id' => $hn->id,
                'status' => 'draft',
                'order_date' => now()->toDateString(),
                'subtotal' => 42000000,
                'discount_amount' => 0,
                'tax_amount' => 4200000,
                'total_amount' => 46200000,
            ]);

            SalesOrderLine::create([
                'company_id' => $companyId,
                'sales_order_id' => $order->id,
                'product_id' => $bySku['NEP-CAM-THUNG']->id,
                'qty' => 100,
                'delivered_qty' => 0,
                'unit_price' => 420000,
                'discount_rate' => 0,
                'tax_rate' => 10,
                'line_total' => 42000000,
            ]);
        }

        // ---- Nhân sự mẫu (để trang HR + dashboard có dữ liệu) ----
        $deptKt = Department::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KT'],
            ['name' => 'Phòng Kế toán'],
        );
        $deptBh = Department::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'BH'],
            ['name' => 'Phòng Bán hàng'],
        );
        $deptKho = Department::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KHO'],
            ['name' => 'Phòng Kho vận'],
        );
        $deptSx = Department::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'SX'],
            ['name' => 'Phòng Sản xuất'],
        );

        $posKt = Position::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KTT'],
            ['name' => 'Kế toán trưởng', 'level' => 'Quản lý'],
        );
        $posNv = Position::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'NV'],
            ['name' => 'Nhân viên', 'level' => 'Nhân viên'],
        );

        $employees = [
            ['NV-001', 'Nguyễn Văn Hùng', $deptKt, $posKt, 25000000, 'ketoan@erp.local'],
            ['NV-002', 'Lê Thị Hoa', $deptBh, $posNv, 12000000, 'banhang@erp.local'],
            ['NV-003', 'Phạm Văn Kho', $deptKho, $posNv, 10000000, 'kho@erp.local'],
            // Tài khoản nhanvien@erp.local do UserSeeder tạo (chạy trước
            // seeder này trong DatabaseSeeder); nếu chưa có tài khoản thì
            // user_id để null, chạy lại seed sau khi có tài khoản sẽ gán.
            ['NV-004', 'Trần Quốc Bảo', $deptSx, $posNv, 9000000, 'nhanvien@erp.local'],
        ];

        foreach ($employees as [$code, $name, $dept, $pos, $salary, $email]) {
            $user = \App\Models\User::where('email', $email)->first();

            $employee = Employee::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'full_name' => $name,
                    'email' => $email,
                    'department_id' => $dept->id,
                    'position_id' => $pos->id,
                    'user_id' => $user?->id,
                    'hire_date' => now()->subYear()->toDateString(),
                    'status' => 'active',
                    'base_salary' => $salary,
                ],
            );

            // Tài khoản tạo sau (vd nhanvien@erp.local): chạy lại seed sẽ
            // gán user_id cho nhân viên đã có mà chưa gắn tài khoản.
            if ($employee->user_id === null && $user !== null) {
                $employee->update(['user_id' => $user->id]);
            }
        }

        // ---- Sản xuất mẫu: 2 trung tâm, 1 BOM + 1 routing cho nước ép cam,
        // 1 lệnh sản xuất nháp (để trang Sản xuất + dropdown routing có dữ liệu) ----
        $wcDongChai = WorkCenter::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'WC-001'],
            ['name' => 'Chuyền đóng chai', 'cost_per_hour' => 150000, 'is_active' => true],
        );
        $wcDanNhan = WorkCenter::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'WC-002'],
            ['name' => 'Tổ dán nhãn & đóng thùng', 'cost_per_hour' => 80000, 'is_active' => true],
        );

        // Nguyên liệu cho BOM (ngoài 10 sản phẩm bán ở trên).
        $camTuoi = Product::firstOrCreate(
            ['company_id' => $companyId, 'sku' => 'NL-CAM-TUOI'],
            [
                'name' => 'Cam tươi', 'uom_id' => $units['kg'],
                'cost_method' => 'avg', 'reorder_min' => 50,
                'sale_price' => 0, 'average_cost' => 25000,
            ],
        );
        $chaiPet = Product::firstOrCreate(
            ['company_id' => $companyId, 'sku' => 'NL-CHAI-PET'],
            [
                'name' => 'Chai nhựa PET 500ml', 'uom_id' => $units['cai'],
                'cost_method' => 'avg', 'reorder_min' => 200,
                'sale_price' => 0, 'average_cost' => 2000,
            ],
        );

        $finished = $bySku['NEP-CAM-THUNG'];

        $bom = Bom::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'BOM-NEP-CAM'],
            ['product_id' => $finished->id, 'version' => 1, 'is_active' => true, 'note' => 'Định mức cho 1 thùng nước ép cam 24 chai.'],
        );

        // 1 thùng thành phẩm: 10kg cam tươi + 24 chai nhựa.
        foreach ([
            ['product' => $camTuoi, 'qty' => 10, 'unit' => $units['kg']],
            ['product' => $chaiPet, 'qty' => 24, 'unit' => $units['cai']],
        ] as $component) {
            BomLine::firstOrCreate(
                ['company_id' => $companyId, 'bom_id' => $bom->id, 'component_product_id' => $component['product']->id],
                ['qty' => $component['qty'], 'unit_id' => $component['unit'], 'scrap_rate' => 0],
            );
        }

        $routing = Routing::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'RTG-NEP-CAM'],
            ['name' => 'Quy trình đóng chai nước ép cam', 'product_id' => $finished->id],
        );

        foreach ([
            [1, 'Ép cam lấy nước', $wcDongChai, 15],
            [2, 'Chiết rót & đóng chai', $wcDongChai, 20],
            [3, 'Dán nhãn & đóng thùng', $wcDanNhan, 10],
        ] as [$sequence, $operationName, $workCenter, $minutes]) {
            RoutingOperation::firstOrCreate(
                ['company_id' => $companyId, 'routing_id' => $routing->id, 'sequence' => $sequence],
                ['work_center_id' => $workCenter->id, 'operation_name' => $operationName, 'standard_minutes' => $minutes],
            );
        }

        if (! ManufacturingOrder::query()->where('company_id', $companyId)->exists()) {
            ManufacturingOrder::create([
                'company_id' => $companyId,
                'code' => $numbering->next('manufacturing_order', $companyId),
                'product_id' => $finished->id,
                'bom_id' => $bom->id,
                'routing_id' => $routing->id,
                'warehouse_id' => $hn->id,
                'qty' => 50,
                'produced_qty' => 0,
                'status' => 'draft',
                'planned_start_date' => now()->addDay()->toDateString(),
                'planned_end_date' => now()->addDays(3)->toDateString(),
                'note' => 'Lệnh sản xuất mẫu — xác nhận rồi bắt đầu để xem công đoạn tự sinh từ routing.',
            ]);
        }
    }
}
