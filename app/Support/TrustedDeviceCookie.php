<?php

namespace App\Support;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceCookie
{
    private const COOKIE_NAME = 'finansys_trusted_device';

    /** @return array{allowed:bool,cookie:?Cookie} */
    public function authorize(Request $request, User $user): array
    {
        if (! config('auth.trusted_device.required')) {
            return ['allowed' => true, 'cookie' => null];
        }

        return DB::transaction(function () use ($request, $user): array {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $token = $request->cookie(self::COOKIE_NAME);
            $device = is_string($token)
                ? $user->trustedDevices()->where('token_hash', hash('sha256', $token))->first()
                : null;

            if ($device !== null) {
                $device->update([
                    'name' => $this->deviceName($request),
                    'last_ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    'last_used_at' => now(),
                ]);

                return ['allowed' => true, 'cookie' => null];
            }

            if ($user->trustedDevices()->count() >= (int) config('auth.trusted_device.maximum', 2)) {
                return ['allowed' => false, 'cookie' => null];
            }

            $newToken = Str::random(64);

            $user->trustedDevices()->create([
                'name' => $this->deviceName($request),
                'token_hash' => hash('sha256', $newToken),
                'last_ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'last_used_at' => now(),
            ]);

            return ['allowed' => true, 'cookie' => $this->make($newToken)];
        }, 3);
    }

    public function isCurrent(Request $request, TrustedDevice $device): bool
    {
        $token = $request->cookie(self::COOKIE_NAME);

        return is_string($token) && hash_equals($device->token_hash, hash('sha256', $token));
    }

    public function forget(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME);
    }

    private function make(string $token): Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $token,
            43200,
            '/',
            config('session.domain'),
            app()->environment('production') || (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    private function deviceName(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'dispositivo desconhecido',
        };

        return $browser.' · '.$system;
    }
}
