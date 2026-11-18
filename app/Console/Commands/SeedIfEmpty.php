<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed dữ liệu mẫu CHỈ khi database còn trống (bảng users chưa có user nào).
 * Dùng trong entrypoint của Docker để lần chạy đầu tiên có dữ liệu demo
 * mà các lần khởi động lại sau không seed trùng.
 */
class SeedIfEmpty extends Command
{
    protected $signature = 'erp:seed-if-empty';

    protected $description = 'Seed dữ liệu mẫu nếu database còn trống (bảng users chưa có user nào).';

    public function handle(): int
    {
        if (DB::table('users')->count() > 0) {
            $this->info('Database đã có dữ liệu, bỏ qua seed.');

            return self::SUCCESS;
        }

        $this->info('Database trống, đang seed dữ liệu mẫu...');
        $this->call('db:seed', ['--force' => true]);
        $this->info('Seed xong. Tài khoản demo: admin@erp.local / password');

        return self::SUCCESS;
    }
}
