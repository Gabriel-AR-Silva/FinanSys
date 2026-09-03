<?php

namespace App\Http\Controllers;

use App\Enums\SocialProvider;
use App\Http\Requests\ProfileUpdateRequest;
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
    public function edit(Request $request): Response
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
        ]);
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
