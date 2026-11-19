<?php

namespace Database\Seeders;

use App\Core\Models\Company;
use App\Core\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản demo — mật khẩu tất cả đều là "password".
 * Tài khoản chính: admin@erp.local (xem README để biết đầy đủ).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        $users = [
            ['Quản trị hệ thống', 'admin@erp.local', 'admin'],
            ['Nguyễn Văn Hùng', 'giamdoc@erp.local', 'giam_doc'],
            ['Trần Thị Lan', 'ketoan@erp.local', 'ke_toan'],
            ['Phạm Văn Kho', 'kho@erp.local', 'kho'],
            ['Lê Thị Hoa', 'banhang@erp.local', 'ban_hang'],
            ['Đỗ Thị Mai', 'nhansu@erp.local', 'nhan_su'],
            ['Trần Văn Nhân', 'nhanvien@erp.local', 'nhan_vien'],
            ['Hoàng Văn Phúc', 'muahang@erp.local', 'mua_hang'],
            ['Vũ Thị Hằng', 'sanxuat@erp.local', 'san_xuat'],
        ];

        foreach ($users as [$name, $email, $roleName]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'current_company_id' => $company?->id,
                ],
            );

            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            if ($company) {
                $user->companies()->syncWithoutDetaching([$company->id]);
            }
        }
    }
}
