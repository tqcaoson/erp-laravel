<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phiếu giao hàng + dòng phiếu giao (module Sales). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->enum('status', ['draft', 'validated', 'cancelled'])->default('draft');
            $table->date('delivery_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'sales_order_id']);
        });

        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'delivery_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_lines');
        Schema::dropIfExists('delivery_orders');
    }
};
