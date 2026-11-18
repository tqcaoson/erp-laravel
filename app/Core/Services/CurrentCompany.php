<?php

namespace App\Core\Services;

use App\Core\Models\Company;
use Illuminate\Support\Facades\Auth;

/**
 * Công ty đang làm việc trong phiên hiện tại.
 *
 * Thứ tự ưu tiên: giá trị override tĩnh (dùng cho job/console/test)
 * -> session 'company_id' -> current_company_id của user -> công ty
 * đầu tiên mà user thuộc về.
 */
class CurrentCompany
{
    protected static ?int $overrideId = null;

    public static function id(): ?int
    {
        if (static::$overrideId !== null) {
            return static::$overrideId;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        try {
            $fromSession = session('company_id');
        } catch (\Throwable) {
            $fromSession = null;
        }

        if ($fromSession) {
            return (int) $fromSession;
        }

        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->current_company_id) {
            return (int) $user->current_company_id;
        }

        return $user->companies()->value('companies.id');
    }

    public static function get(): ?Company
    {
        $id = static::id();

        return $id ? Company::find($id) : null;
    }

    /** Ép công ty hiện tại (dùng trong job, console, test). */
    public static function setId(?int $companyId): void
    {
        static::$overrideId = $companyId;
    }

    /** Đổi công ty cho phiên web hiện tại. */
    public static function switchTo(int $companyId): void
    {
        session(['company_id' => $companyId]);

        if (Auth::check()) {
            Auth::user()->forceFill(['current_company_id' => $companyId])->save();
        }
    }
}
