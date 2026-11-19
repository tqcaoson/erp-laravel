<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột invoiced_qty vào dòng đơn bán: số lượng đã lập hóa đơn.
 * Phần đã giao chưa lập hóa đơn = delivered_qty − invoiced_qty, dùng để
 * lập hóa đơn từng phần và chống lập trùng hóa đơn cho cùng một lượng hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('invoiced_qty', 15, 2)->default(0)->after('delivered_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn('invoiced_qty');
        });
    }
};
