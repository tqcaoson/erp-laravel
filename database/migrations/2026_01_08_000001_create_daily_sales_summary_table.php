<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tổng hợp doanh thu theo ngày (mục 4.7 thiết kế).
 * Cập nhật bằng UpdateDailySummaryJob, dashboard đọc từ đây thay vì
 * quét trực tiếp bảng invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_cogs', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'date']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summary');
    }
};
