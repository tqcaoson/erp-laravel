<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lương đợt 2 (20/11/2026):
 * - employees: dependents_count (số người phụ thuộc để giảm trừ gia cảnh
 *   khi tính thuế TNCN) + termination_date (ngày nghỉ việc, để tính lương
 *   prorat cho nhân viên nghỉ giữa tháng).
 * - payslips: standard_working_days (ngày công chuẩn của tháng),
 *   worked_days (ngày công hưởng lương thực tế), unpaid_leave_days
 *   (ngày nghỉ không lương + vắng mặt đã trừ công).
 *
 * Không thêm index/khóa nào nên không có identifier mới cần audit
 * giới hạn 64 ký tự của MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->integer('dependents_count')->default(0);
            $table->date('termination_date')->nullable();
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('standard_working_days', 18, 2)->default(0);
            $table->decimal('worked_days', 18, 2)->default(0);
            $table->decimal('unpaid_leave_days', 18, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['standard_working_days', 'worked_days', 'unpaid_leave_days']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['dependents_count', 'termination_date']);
        });
    }
};
