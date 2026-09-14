<?php

namespace App\Http\Controllers;

use App\Enums\SocialProvider;
use App\Models\TrustedDevice;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\TrustedDeviceCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, TrustedDeviceCookie $trustedDeviceCookie): Response
    {
        return Inertia::render('Profile/Edit', [
            'googleAuthenticationEnabled' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect'))
                && filled(config('services.google.allowed_email')),
            'googleIdentity' => $request->user()
                ->socialIdentities()
                ->where('provider', SocialProvider::Google)
                ->first(['email', 'linked_at']),
            'trustedDevices' => $request->user()
                ->trustedDevices()
                ->latest('last_used_at')
                ->get(['id', 'name', 'last_ip_address', 'last_used_at'])
                ->map(fn (TrustedDevice $device): array => [
                    ...$device->toArray(),
                    'current' => $trustedDeviceCookie->isCurrent($request, $device),
                ]),
        ]);
    }

    public function destroyTrustedDevice(Request $request, TrustedDevice $device, TrustedDeviceCookie $trustedDeviceCookie): RedirectResponse
    {
        if ((int) $device->user_id !== (int) $request->user()->getKey()) {
            abort(404);
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $isCurrent = $trustedDeviceCookie->isCurrent($request, $device);
        $device->delete();

        $response = back()->with('success', 'Dispositivo removido da lista de confiança.');

        if ($isCurrent) {
            $response->withCookie($trustedDeviceCookie->forget());
        }

        return $response;
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        $request->user()->save();

        return Redirect::route('profile.edit');
    }
}
