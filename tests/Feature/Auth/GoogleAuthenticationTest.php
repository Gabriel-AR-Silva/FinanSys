<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\SocialProvider;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_EMAIL = 'gabrielsilva.contato9@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'http://localhost/auth/google/callback',
            'allowed_email' => self::ALLOWED_EMAIL,
            'bootstrap_user_email' => 'temporary@example.com',
        ]);
    }

    public function test_login_redirect_stores_the_intent_without_creating_a_user(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get(route('google.login'));

        $response->assertRedirect('https://accounts.google.test');
        $response->assertSessionHas('google_auth_intent', 'login');
        $this->assertSame(0, User::query()->count());
    }

    public function test_linking_google_migrates_the_existing_user_identity_and_audits_the_change(): void
    {
        $user = User::factory()->create(['email' => 'temporary@example.com']);
        $this->mockGoogleUser($this->googleUser());

        $response = $this->actingAs($user)
            ->withSession(['google_auth_intent' => 'link'])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_identities', [
            'user_id' => $user->id,
            'provider' => SocialProvider::Google->value,
            'provider_user_id' => 'google-user-123',
            'email' => self::ALLOWED_EMAIL,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => self::ALLOWED_EMAIL]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditAction::Linked->value]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_linking_rejects_an_unverified_google_email(): void
    {
        $user = User::factory()->create();
        $this->mockGoogleUser($this->googleUser(verified: false));

        $response = $this->actingAs($user)
            ->withSession(['google_auth_intent' => 'link'])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error', 'Não foi possível autenticar esta conta Google.');
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_linking_rejects_a_google_email_outside_the_allowlist(): void
    {
        $user = User::factory()->create();
        $this->mockGoogleUser($this->googleUser(email: 'intruder@example.com'));

        $response = $this->actingAs($user)
            ->withSession(['google_auth_intent' => 'link'])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error', 'Não foi possível autenticar esta conta Google.');
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_google_login_authenticates_only_a_previously_linked_identity(): void
    {
        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);
        SocialIdentity::factory()->for($user)->create([
            'provider_user_id' => 'google-user-123',
            'email' => self::ALLOWED_EMAIL,
        ]);
        $this->mockGoogleUser($this->googleUser());

        $response = $this->withSession(['google_auth_intent' => 'login'])
            ->get(route('google.callback'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_first_google_login_links_the_configured_existing_user_without_replacing_it(): void
    {
        $user = User::factory()->create(['email' => 'temporary@example.com']);
        $this->mockGoogleUser($this->googleUser());

        $response = $this->withSession(['google_auth_intent' => 'login'])
            ->get(route('google.callback'));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('social_identities', [
            'user_id' => $user->id,
            'provider' => SocialProvider::Google->value,
            'provider_user_id' => 'google-user-123',
            'email' => self::ALLOWED_EMAIL,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => self::ALLOWED_EMAIL]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditAction::Linked->value]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_first_google_login_does_not_link_a_different_local_user(): void
    {
        $user = User::factory()->create(['email' => 'different@example.com']);
        $this->mockGoogleUser($this->googleUser());

        $response = $this->withSession(['google_auth_intent' => 'login'])
            ->get(route('google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Não foi possível autenticar esta conta Google.');
        $this->assertDatabaseCount('social_identities', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'different@example.com']);
    }

    public function test_google_login_never_creates_an_unlinked_user(): void
    {
        $this->mockGoogleUser($this->googleUser());

        $response = $this->withSession(['google_auth_intent' => 'login'])
            ->get(route('google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Não foi possível autenticar esta conta Google.');
        $this->assertSame(0, User::query()->count());
    }

    public function test_callback_without_a_server_side_intent_is_rejected(): void
    {
        $response = $this->get(route('google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_linked_google_identity_can_be_removed_after_password_confirmation(): void
    {
        $user = User::factory()->create();
        $identity = SocialIdentity::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('google.unlink'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        $this->assertModelMissing($identity);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditAction::Unlinked->value]);
    }

    private function mockGoogleUser(GoogleUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    private function googleUser(string $email = self::ALLOWED_EMAIL, bool $verified = true): GoogleUser
    {
        return (new GoogleUser)->setRaw([
            'sub' => 'google-user-123',
            'email' => $email,
            'email_verified' => $verified,
        ])->map([
            'id' => 'google-user-123',
            'name' => 'Gabriel Silva',
            'email' => $email,
        ]);
    }
}
