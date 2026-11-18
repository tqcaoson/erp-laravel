<?php

namespace App\Http\Middleware;

use App\Core\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props dùng chung cho mọi trang Inertia: user, danh sách quyền,
     * công ty hiện tại + danh sách công ty (để đổi ở topbar), flash message.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles()->pluck('roles.name')->all(),
                    'permissions' => $user->permissionNames(),
                ] : null,
            ],
            'company' => fn () => $user ? [
                'current' => CurrentCompany::get()?->only(['id', 'name', 'base_currency']),
                'list' => $user->companies()->select('companies.id', 'companies.name')->get(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
