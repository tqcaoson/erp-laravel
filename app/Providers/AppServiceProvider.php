<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Role admin luôn được phép mọi thao tác (bỏ qua Policy).
        Gate::before(function (User $user) {
            return $user->hasRole('admin') ? true : null;
        });

        $this->registerModulePolicies();
    }

    /**
     * Đăng ký tập trung Policy của các module (modular monolith nên
     * Laravel không tự phát hiện Policy theo quy ước tên được).
     * Route đã chặn bằng middleware 'permission:...'; Policy là lớp
     * phân quyền thứ hai cho controller dùng $this->authorize().
     */
    protected function registerModulePolicies(): void
    {
        $policies = [
            // Kế toán
            \App\Modules\Accounting\Models\JournalEntry::class => \App\Modules\Accounting\Policies\JournalEntryPolicy::class,
            \App\Modules\Accounting\Models\Invoice::class => \App\Modules\Accounting\Policies\InvoicePolicy::class,
            // Mua hàng
            \App\Modules\Procurement\Models\Supplier::class => \App\Modules\Procurement\Policies\SupplierPolicy::class,
            \App\Modules\Procurement\Models\PurchaseOrder::class => \App\Modules\Procurement\Policies\PurchaseOrderPolicy::class,
            \App\Modules\Procurement\Models\PurchaseRequest::class => \App\Modules\Procurement\Policies\PurchaseRequestPolicy::class,
            \App\Modules\Procurement\Models\GoodsReceipt::class => \App\Modules\Procurement\Policies\GoodsReceiptPolicy::class,
            // Kho (StockPolicy gắn với StockMove — policy thao tác tồn kho chung)
            \App\Modules\Inventory\Models\Product::class => \App\Modules\Inventory\Policies\ProductPolicy::class,
            \App\Modules\Inventory\Models\Warehouse::class => \App\Modules\Inventory\Policies\WarehousePolicy::class,
            \App\Modules\Inventory\Models\StockMove::class => \App\Modules\Inventory\Policies\StockPolicy::class,
            // Bán hàng
            \App\Modules\Sales\Models\SalesOrder::class => \App\Modules\Sales\Policies\SalesOrderPolicy::class,
            \App\Modules\Sales\Models\Customer::class => \App\Modules\Sales\Policies\CustomerPolicy::class,
            \App\Modules\Sales\Models\Quotation::class => \App\Modules\Sales\Policies\QuotationPolicy::class,
            \App\Modules\Sales\Models\DeliveryOrder::class => \App\Modules\Sales\Policies\DeliveryOrderPolicy::class,
            // Sản xuất
            \App\Modules\Manufacturing\Models\Bom::class => \App\Modules\Manufacturing\Policies\BomPolicy::class,
            \App\Modules\Manufacturing\Models\ManufacturingOrder::class => \App\Modules\Manufacturing\Policies\ManufacturingOrderPolicy::class,
            // Nhân sự
            \App\Modules\HR\Models\Employee::class => \App\Modules\HR\Policies\EmployeePolicy::class,
            \App\Modules\HR\Models\LeaveRequest::class => \App\Modules\HR\Policies\LeaveRequestPolicy::class,
            \App\Modules\HR\Models\PayrollRun::class => \App\Modules\HR\Policies\PayrollRunPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
