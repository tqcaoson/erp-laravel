<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/**
 * Kiểm tra thực tế: backend có chặn user không có quyền không.
 * Từng là file chẩn đoán tạm; giữ lại làm test hồi quy cho lỗi
 * wildcard '.view' ở RolePermissionSeeder (19/11/2026): mọi role có
 * 1 quyền xem cụ thể từng bị cấp nhầm toàn bộ quyền xem.
 */
class PermissionEnforcementProbeTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_kho_không_vào_được_kế_toán_bán_hàng_nhân_sự(): void
    {
        $this->seedBaseData();
        $kho = $this->user('kho@erp.local');

        $this->actingAs($kho)->get('/accounting/journal-entries')->assertForbidden();
        $this->actingAs($kho)->get('/sales/orders')->assertForbidden();
        $this->actingAs($kho)->get('/hr/employees')->assertForbidden();
        $this->actingAs($kho)->get('/reports')->assertForbidden();
        $this->actingAs($kho)->get('/inventory/stock')->assertOk();
        $this->actingAs($kho)->get('/dashboard')->assertOk();
    }

    public function test_nhân_sự_không_vào_được_kế_toán_và_kho(): void
    {
        $this->seedBaseData();
        $ns = $this->user('nhansu@erp.local');

        $this->actingAs($ns)->get('/accounting/journal-entries')->assertForbidden();
        $this->actingAs($ns)->get('/inventory/stock')->assertForbidden();
        $this->actingAs($ns)->get('/hr/employees')->assertOk();
    }

    public function test_kế_toán_không_vào_được_kho_và_nhân_sự(): void
    {
        $this->seedBaseData();
        $kt = $this->user('ketoan@erp.local');

        $this->actingAs($kt)->get('/inventory/stock')->assertForbidden();
        $this->actingAs($kt)->get('/hr/employees')->assertForbidden();
        $this->actingAs($kt)->get('/accounting/journal-entries')->assertOk();
    }

    public function test_bán_hàng_không_vào_được_kế_toán_và_kho_nhập_xuất(): void
    {
        $this->seedBaseData();
        $bh = $this->user('banhang@erp.local');

        $this->actingAs($bh)->get('/accounting/journal-entries')->assertForbidden();
        $this->actingAs($bh)->get('/inventory/stock/move')->assertForbidden();
        $this->actingAs($bh)->get('/sales/orders')->assertOk();
        $this->actingAs($bh)->get('/inventory/products')->assertOk();
    }

    public function test_giám_đốc_xem_được_mọi_trang_xem(): void
    {
        $this->seedBaseData();
        $gd = $this->user('giamdoc@erp.local');

        $this->actingAs($gd)->get('/accounting/journal-entries')->assertOk();
        $this->actingAs($gd)->get('/inventory/stock')->assertOk();
        $this->actingAs($gd)->get('/hr/employees')->assertOk();
    }
}
