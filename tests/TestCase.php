<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test không phụ thuộc vào bản build frontend: các test HTTP render
        // trang Inertia đầy đủ, mà layout gọi @vite — không có manifest
        // (vd checkout mới chưa build, hoặc bản zip loại trừ public/build)
        // thì các test đó 500 dù code đúng. Tắt Vite cho test để suite
        // chạy được ở mọi môi trường; bản build thật vẫn được kiểm chứng
        // riêng bằng `npm run build`.
        $this->withoutVite();
    }
}
