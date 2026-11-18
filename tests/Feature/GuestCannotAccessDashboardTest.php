<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/** Khách chưa đăng nhập không vào được dashboard, bị chuyển về trang login. */
class GuestCannotAccessDashboardTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    public function test_khách_chưa_đăng_nhập_bị_chuyển_về_trang_login(): void
    {
        $this->seedBaseData();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/sales/orders')->assertRedirect('/login');
        $this->get('/inventory/products')->assertRedirect('/login');
    }

    public function test_user_đã_đăng_nhập_vào_được_dashboard(): void
    {
        $this->seedBaseData();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk();
    }
}
