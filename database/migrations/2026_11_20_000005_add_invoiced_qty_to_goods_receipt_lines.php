<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột invoiced_qty vào dòng phiếu nhận hàng: số lượng đã lập
 * hóa đơn mua. Phần đã nhận chưa lập hóa đơn = qty − invoiced_qty,
 * dùng để lập hóa đơn từng phần và chống lập trùng hóa đơn cho cùng
 * một lượng hàng (cùng mẫu với invoiced_qty của dòng đơn bán).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->decimal('invoiced_qty', 18, 4)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('invoiced_qty');
        });
    }
};
