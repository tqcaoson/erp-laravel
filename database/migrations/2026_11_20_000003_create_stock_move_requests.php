<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu kho tay chờ duyệt (module Inventory).
 * Phiếu nhập/xuất/chuyển kho tạo tay không ghi tồn ngay nữa: lưu vào bảng
 * này ở trạng thái pending, người có quyền inventory.stock.confirm duyệt
 * thì mới ghi tồn qua StockService (source_type 'manual', source_id = id
 * phiếu). Mọi tên bảng/cột/index/FK đều ≤ 64 ký tự (giới hạn MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_move_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 20); // receive | issue | transfer
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->decimal('qty', 15, 2);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'smr_co_status_idx');
            $table->index(['company_id', 'product_id'], 'smr_co_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_move_requests');
    }
};
