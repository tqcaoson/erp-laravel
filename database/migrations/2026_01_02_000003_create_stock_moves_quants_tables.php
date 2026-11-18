<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ kho (stock_moves, chỉ thêm không sửa) và tồn tổng hợp (stock_quants).
 * stock_moves ĐÚNG DDL mục 5.3 + warehouse_id, updated_at, created_by/updated_by.
 * Quy ước dấu qty: dương = nhập kho, âm = xuất kho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->dateTime('moved_at');
            // Cột bổ sung theo hợp đồng.
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'moved_at'], 'idx_moves_product');
            $table->index(['source_type', 'source_id'], 'idx_moves_source');
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('stock_quants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('reserved_qty', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Lưu ý SQLite: cột NULL trong unique index được coi là khác nhau,
            // nên ràng buộc này chỉ chặn trùng tuyệt đối khi đủ location + lot.
            // Service vẫn tự kiểm tra tồn tại trước khi tạo mới.
            $table->unique(['product_id', 'warehouse_id', 'location_id', 'lot_id'], 'uq_stock_quants');
            $table->index(['company_id', 'product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quants');
        Schema::dropIfExists('stock_moves');
    }
};
