<?php

namespace App\Http\Controllers;

use App\Enums\SocialProvider;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
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

    public function avatar(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        // A foto pertence somente ao usuário autenticado e não depende de public/storage.
        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            try {
                $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
            } catch (Throwable $exception) {
                report($exception);
            }

            if (! $newAvatarPath) {
                throw ValidationException::withMessages([
                    'avatar' => 'Não foi possível gravar a foto. Verifique as permissões de storage/app/public no servidor.',
                ]);
            }

            $user->avatar_path = $newAvatarPath;
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar_path = null;
        }

        $user->fill($request->safe()->only(['name', 'email']));

        try {
            $user->save();
        } catch (Throwable $exception) {
            if ($newAvatarPath) {
                try {
                    Storage::disk('public')->delete($newAvatarPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        // Falhas ao limpar arquivos antigos não devem transformar um salvamento concluído em HTTP 500.
        if ($oldAvatarPath && $oldAvatarPath !== $user->avatar_path) {
            try {
                if (! Storage::disk('public')->delete($oldAvatarPath)) {
                    report(new \RuntimeException('Não foi possível remover a foto de perfil anterior.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return Redirect::route('profile.edit');
    }
}
