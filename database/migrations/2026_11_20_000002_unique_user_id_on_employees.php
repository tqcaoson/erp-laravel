<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 tài khoản user tối đa gắn với 1 hồ sơ nhân viên (phục vụ các trang
 * tự phục vụ "của mình": chấm công, nghỉ phép, phiếu lương).
 * Tên index mặc định employees_user_id_unique (24 ký tự, dưới giới hạn
 * 64 ký tự của MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_user_id_unique');
        });
    }
};
