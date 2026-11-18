<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tổng hợp xuất nhập tồn theo ngày/sản phẩm/kho.
 * FK tới products/warehouses (tiền tố 02, chạy trước) nên CÓ khóa ngoại
 * theo quy tắc mục 4 hợp đồng. Nếu module Inventory chưa migrate thì
 * migration này sẽ lỗi vì thiếu bảng — đó là thứ tự chạy đã định sẵn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stock_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('opening_qty', 18, 4)->default(0);
            $table->decimal('in_qty', 18, 4)->default(0);
            $table->decimal('out_qty', 18, 4)->default(0);
            $table->decimal('closing_qty', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'date', 'product_id', 'warehouse_id'], 'dss_co_date_prod_wh_unique');
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stock_summary');
    }
};
