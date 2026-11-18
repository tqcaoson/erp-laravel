<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mở rộng bảng users: công ty đang làm việc + trạng thái hoạt động. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_company_id')->nullable()->after('email')
                ->constrained('companies')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('current_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
            $table->dropColumn('is_active');
        });
    }
};
