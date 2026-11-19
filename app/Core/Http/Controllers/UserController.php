<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Role;
use App\Core\Services\CurrentCompany;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Quản lý tài khoản người dùng (admin): danh sách, tạo, sửa, xóa,
 * vô hiệu/kích hoạt lại. Quyền core.user.view (xem) / core.user.manage
 * (thao tác) gắn ở routes/web.php.
 */
class UserController
{
    public function index(Request $request)
    {
        $users = User::with('roles:id,name,label')
            ->when($request->input('filter.search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('filter.is_active') !== null && $request->input('filter.is_active') !== '', function ($q) use ($request) {
                $q->where('is_active', (bool) $request->input('filter.is_active'));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Tên là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được dùng cho tài khoản khác.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'roles.*.exists' => 'Vai trò đã chọn không tồn tại.',
        ]);

        $companyId = CurrentCompany::id();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'current_company_id' => $companyId,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        if ($companyId) {
            $user->companies()->syncWithoutDetaching([$companyId]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Đã tạo tài khoản {$user->email}.");
    }

    public function edit(User $user)
    {
        $user->load('roles:id,name,label');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => Role::orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Tên là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được dùng cho tài khoản khác.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'roles.*.exists' => 'Vai trò đã chọn không tồn tại.',
        ]);

        // Không được tự vô hiệu chính mình (kẻo khóa luôn tài khoản admin đang dùng).
        if ($user->is($request->user()) && array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            return back()->with('error', 'Bạn không thể tự vô hiệu tài khoản của chính mình.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        // Mật khẩu để trống = giữ nguyên mật khẩu cũ.
        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (array_key_exists('roles', $validated)) {
            $user->roles()->sync($validated['roles'] ?? []);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Đã cập nhật tài khoản {$user->email}.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Bạn không thể tự xóa tài khoản của chính mình.');
        }

        // Pivot role_user / company_user đã cascadeOnDelete ở migration
        // nên xóa user là đủ; detach tay thêm để an toàn nếu DB cũ chưa
        // có cascade, và để xóa rõ ràng ngay cả khi FK chưa kịp chạy.
        $user->roles()->detach();
        $user->companies()->detach();

        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Không xóa được tài khoản này vì còn dữ liệu liên quan (ví dụ hoạt động bán hàng). Hãy vô hiệu tài khoản thay vì xóa.');
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Đã xóa tài khoản {$user->email}.");
    }

    /** Bật/tắt trạng thái hoạt động của tài khoản (không được tự vô hiệu mình). */
    public function toggleActive(Request $request, User $user)
    {
        if ($user->is($request->user()) && $user->is_active) {
            return back()->with('error', 'Bạn không thể tự vô hiệu tài khoản của chính mình.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'kích hoạt lại' : 'vô hiệu';

        return back()->with('success', "Đã {$status} tài khoản {$user->email}.");
    }
}
