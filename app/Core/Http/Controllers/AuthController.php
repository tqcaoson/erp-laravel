<?php

namespace App\Core\Http\Controllers;

use App\Core\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Đăng nhập / đăng xuất bằng session của Laravel (không dùng Breeze). */
class AuthController
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Chọn công ty mặc định cho phiên làm việc.
        $companyId = Auth::user()->current_company_id
            ?? Auth::user()->companies()->value('companies.id');

        if ($companyId) {
            CurrentCompany::switchTo((int) $companyId);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
