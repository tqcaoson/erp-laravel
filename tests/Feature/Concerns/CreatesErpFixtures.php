<?php

namespace Tests\Feature\Concerns;

use App\Core\Models\Company;
use App\Core\Models\Unit;
use App\Core\Services\CurrentCompany;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture dùng chung cho test feature: công ty, user admin, kho, sản phẩm.
 * Mỗi test chạy trên database SQLite :memory: mới (RefreshDatabase).
 */
trait CreatesErpFixtures
{
    protected Company $company;

    protected User $admin;

    /** Seed dữ liệu nền tối thiểu + tạo công ty/user mặc định. */
    protected function seedBaseData(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->company = Company::firstOrFail();
        $this->admin = User::where('email', 'admin@erp.local')->firstOrFail();

        CurrentCompany::setId($this->company->id);
    }

    protected function createWarehouse(string $code = 'KHO-TEST'): Warehouse
    {
        return Warehouse::create([
            'company_id' => $this->company->id,
            'code' => $code,
            'name' => 'Kho kiểm thử '.$code,
        ]);
    }

    protected function createProduct(string $sku = 'SP-TEST', float $salePrice = 100000, float $reorderMin = 0): Product
    {
        $unit = Unit::query()->where('code', 'cai')->firstOrFail();

        return Product::create([
            'company_id' => $this->company->id,
            'sku' => $sku,
            'name' => 'Sản phẩm kiểm thử '.$sku,
            'uom_id' => $unit->id,
            'cost_method' => 'avg',
            'reorder_min' => $reorderMin,
            'sale_price' => $salePrice,
            'average_cost' => 0,
        ]);
    }

    /** Tạo user thường với 1 role cho trước (để test phân quyền). */
    protected function createUserWithRole(string $email, string $roleName): User
    {
        $user = User::create([
            'name' => 'User '.$roleName,
            'email' => $email,
            'password' => Hash::make('password'),
            'current_company_id' => $this->company->id,
        ]);

        $user->companies()->attach($this->company->id);
        $user->roles()->attach(
            \App\Core\Models\Role::where('name', $roleName)->firstOrFail()->id
        );

        return $user;
    }
}
