<?php

namespace App\Support;

use App\Models\User;

final class LocalizedDemoData
{
    public static function userName(User $user): string
    {
        return self::translatedOrFallback(
            'agencyos.demo.users.'.($user->username ?? ''),
            $user->full_name,
        );
    }

    public static function roleName(User $user): string
    {
        $code = $user->role?->code;

        return self::translatedOrFallback(
            'agencyos.roles.'.$code,
            $user->role?->name ?? '',
        );
    }

    /**
     * Localized mock content used by the foundation dashboard.
     * Real, user-entered entity names are never changed by this helper.
     */
    public static function dashboard(): array
    {
        $data = trans('agencyos.demo.dashboard');

        return is_array($data) ? $data : [];
    }

    private static function translatedOrFallback(string $key, string $fallback): string
    {
        $translated = trans($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $fallback;
    }
}
