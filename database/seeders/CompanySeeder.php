<?php

namespace Database\Seeders;

use App\Core\Models\Company;
use App\Core\Models\Currency;
use App\Core\Models\Unit;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['tax_code' => '0101234567'],
            [
                'name' => 'Công ty TNHH Thương mại An Phát',
                'base_currency' => 'VND',
                'address' => 'Số 12, đường Nguyễn Trãi, quận Thanh Xuân, Hà Nội',
                'phone' => '024 3556 7890',
                'email' => 'lienhe@anphat.local',
            ],
        );

        // Đơn vị tính dùng chung cho mọi công ty (company_id = null).
        foreach ([
            ['cai', 'Cái'],
            ['hop', 'Hộp'],
            ['thung', 'Thùng'],
            ['chai', 'Chai'],
            ['kg', 'Kilogram'],
            ['lit', 'Lít'],
            ['bo', 'Bộ'],
            ['goi', 'Gói'],
        ] as [$code, $name]) {
            Unit::firstOrCreate(['code' => $code, 'company_id' => null], ['name' => $name]);
        }

        // Tiền tệ dùng chung.
        Currency::firstOrCreate(['code' => 'VND', 'company_id' => null], [
            'name' => 'Đồng Việt Nam', 'symbol' => '₫', 'exchange_rate' => 1,
        ]);
        Currency::firstOrCreate(['code' => 'USD', 'company_id' => null], [
            'name' => 'Đô la Mỹ', 'symbol' => '$', 'exchange_rate' => 25000,
        ]);
    }
}
