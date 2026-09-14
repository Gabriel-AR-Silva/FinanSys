<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceCookie
{
    private const COOKIE_NAME = 'finansys_trusted_device';

    private const DURATION_MINUTES = 43200;

    public function allowsPasswordLogin(Request $request, User $user): bool
    {
        if (! config('auth.trusted_device.required')) {
            return true;
        }

        $value = $request->cookie(self::COOKIE_NAME);

        return is_string($value) && hash_equals($this->valueFor($user), $value);
    }

    public function make(User $user): Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $this->valueFor($user),
            self::DURATION_MINUTES,
            '/',
            config('session.domain'),
            app()->environment('production') || (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    private function valueFor(User $user): string
    {
        $payload = implode('|', [
            $user->getKey(),
            hash('sha256', (string) $user->password),
        ]);

        return $payload.'|'.hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
