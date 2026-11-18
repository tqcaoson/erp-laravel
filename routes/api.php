<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// Health check: kiểm tra kết nối DB + Redis (mục 11.3 của thiết kế).
Route::get('/health', function () {
    $checks = ['app' => 'ok'];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'fail: '.$e->getMessage();
    }

    try {
        Redis::connection()->ping();
        $checks['redis'] = 'ok';
    } catch (\Throwable $e) {
        $checks['redis'] = 'fail: '.$e->getMessage();
    }

    $healthy = ! str_contains(implode(' ', $checks), 'fail');

    return response()->json($checks, $healthy ? 200 : 503);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// API /api/v1 của từng module nằm trong routes.php của module đó
// (app/Modules/*/routes.php), dùng prefix 'api/v1' + middleware auth:sanctum.
