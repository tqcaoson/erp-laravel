<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sản phẩm (ĐÚNG DDL mục 5.3 của thiết kế + cột bổ sung theo hợp đồng),
 * đơn vị quy đổi và lô hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->foreignId('uom_id')->constrained('units');
            $table->enum('cost_method', ['avg', 'fifo'])->default('avg');
            $table->boolean('track_lot')->default(false);
            $table->boolean('track_serial')->default(false);
            $table->decimal('reorder_min', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 2)->default(0);
            // Cột bổ sung theo hợp đồng mục 5 (Inventory).
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('conversion_factor', 18, 4)->default(1);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->date('expiry_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'code']);
            $table->index(['company_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
    }
};
