<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lệnh sản xuất + công đoạn + nhật ký sản xuất — module Manufacturing. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('bom_id')->nullable()->constrained('boms')->nullOnDelete();
            $table->foreignId('routing_id')->nullable()->constrained('routings')->nullOnDelete();
            // Sales (tiền tố 05) chạy trước nên CÓ FK tới sales_orders.
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            // Kho xuất nguyên liệu / nhập thành phẩm (cột thêm ngoài hợp đồng, cần cho StockService).
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('produced_qty', 18, 4)->default(0);
            $table->enum('status', ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'product_id']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->string('operation_name');
            $table->integer('sequence')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->decimal('planned_minutes', 18, 2)->default(0);
            $table->decimal('actual_minutes', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'manufacturing_order_id']);
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty_produced', 18, 4)->default(0);
            $table->decimal('qty_scrap', 18, 4)->default(0);
            // HR chạy sau (tiền tố 07) nên KHÔNG tạo FK tới employees.
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->dateTime('logged_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'manufacturing_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('manufacturing_orders');
    }
};
