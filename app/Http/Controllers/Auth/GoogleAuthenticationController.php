<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthenticationController extends Controller
{
    private const LOGIN_INTENT = 'login';

    private const LINK_INTENT = 'link';

    public function redirectForLogin(Request $request): RedirectResponse|SymfonyRedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')->with('error', 'O login com Google ainda não está configurado.');
        }

        $request->session()->put('google_auth_intent', self::LOGIN_INTENT);

        return Socialite::driver(SocialProvider::Google->value)->redirect();
    }

    public function redirectForLink(Request $request): RedirectResponse|SymfonyRedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('profile.edit')->with('error', 'Configure as credenciais do Google antes de vincular a conta.');
        }

        $request->session()->put('google_auth_intent', self::LINK_INTENT);

        return Socialite::driver(SocialProvider::Google->value)->redirect();
    }

    public function callback(Request $request, AuditRecorder $auditRecorder): RedirectResponse
    {
        $intent = $request->session()->pull('google_auth_intent');

        if (! in_array($intent, [self::LOGIN_INTENT, self::LINK_INTENT], true) || ! $this->isConfigured()) {
            return $this->authenticationFailed($intent);
        }

        try {
            $googleUser = Socialite::driver(SocialProvider::Google->value)->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->authenticationFailed($intent);
        }

        if (! $this->isAllowedIdentity($googleUser)) {
            return $this->authenticationFailed($intent);
        }

        return $intent === self::LINK_INTENT
            ? $this->link($request, $googleUser, $auditRecorder)
            : $this->login($request, $googleUser, $auditRecorder);
    }

    public function destroy(Request $request, AuditRecorder $auditRecorder): RedirectResponse
    {
        $identity = $request->user()->socialIdentities()
            ->where('provider', SocialProvider::Google)
            ->first();

        if ($identity === null) {
            return redirect()->route('profile.edit')->with('warning', 'Nenhuma conta Google estava vinculada.');
        }

        DB::transaction(function () use ($auditRecorder, $identity, $request): void {
            $auditRecorder->record($request->user(), AuditAction::Unlinked, $identity);
            $identity->delete();
        });

        return redirect()->route('profile.edit')->with('success', 'Conta Google desvinculada com segurança.');
    }

    private function login(Request $request, SocialiteUser $googleUser, AuditRecorder $auditRecorder): RedirectResponse
    {
        $identity = SocialIdentity::query()
            ->with('user')
            ->where('provider', SocialProvider::Google)
            ->where('provider_user_id', $googleUser->getId())
            ->first();

        if ($identity === null) {
            $identity = $this->bootstrapIdentity($googleUser, $auditRecorder);
        }

        if ($identity === null || ! hash_equals(mb_strtolower($identity->email), mb_strtolower((string) $googleUser->getEmail()))) {
            return $this->authenticationFailed(self::LOGIN_INTENT);
        }

        Auth::login($identity->user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function bootstrapIdentity(SocialiteUser $googleUser, AuditRecorder $auditRecorder): ?SocialIdentity
    {
        $bootstrapEmail = mb_strtolower((string) config('services.google.bootstrap_user_email'));

        if ($bootstrapEmail === '') {
            return null;
        }

        try {
            return DB::transaction(function () use ($auditRecorder, $bootstrapEmail, $googleUser): ?SocialIdentity {
                $user = User::query()
                    ->where('email', $bootstrapEmail)
                    ->lockForUpdate()
                    ->first();

                if ($user === null || $user->socialIdentities()->where('provider', SocialProvider::Google)->exists()) {
                    return null;
                }

                $email = mb_strtolower((string) $googleUser->getEmail());
                $identity = $user->socialIdentities()->create([
                    'provider' => SocialProvider::Google,
                    'provider_user_id' => (string) $googleUser->getId(),
                    'email' => $email,
                    'linked_at' => now(),
                ]);

                $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
                $auditRecorder->record($user, AuditAction::Linked, $identity);

                return $identity->setRelation('user', $user);
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function link(Request $request, SocialiteUser $googleUser, AuditRecorder $auditRecorder): RedirectResponse
    {
        if (! Auth::check()) {
            return $this->authenticationFailed(self::LINK_INTENT);
        }

        try {
            DB::transaction(function () use ($auditRecorder, $googleUser, $request): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
                $email = mb_strtolower((string) $googleUser->getEmail());
                $identity = $user->socialIdentities()->updateOrCreate(
                    ['provider' => SocialProvider::Google],
                    [
                        'provider_user_id' => (string) $googleUser->getId(),
                        'email' => $email,
                        'linked_at' => now(),
                    ],
                );

                $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
                $auditRecorder->record($user, AuditAction::Linked, $identity);
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('profile.edit')->with('error', 'Esta conta Google já está vinculada a outro usuário.');
        }

        return redirect()->route('profile.edit')->with('success', 'Conta Google vinculada. Seu e-mail de acesso também foi atualizado.');
    }

    private function isAllowedIdentity(SocialiteUser $googleUser): bool
    {
        $allowedEmail = mb_strtolower((string) config('services.google.allowed_email'));
        $googleEmail = mb_strtolower((string) $googleUser->getEmail());
        $emailVerified = filter_var($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false, FILTER_VALIDATE_BOOL);

        return $googleUser->getId() !== null
            && $emailVerified
            && $allowedEmail !== ''
            && hash_equals($allowedEmail, $googleEmail);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'))
            && filled(config('services.google.allowed_email'));
    }

    private function authenticationFailed(?string $intent): RedirectResponse
    {
        $route = $intent === self::LINK_INTENT && Auth::check() ? 'profile.edit' : 'login';

        return redirect()->route($route)->with('error', 'Não foi possível autenticar esta conta Google.');
    }
}
