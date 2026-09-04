<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        config()->set('services.google.client_id', null);

        $response = $this->get('/login');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('canResetPassword', true)
                ->where('googleAuthenticationEnabled', false)
                ->where('googleError', null)
                ->where('status', null));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'E-mail ou senha incorretos.',
        ]);
    }

    public function test_login_validation_messages_are_presented_in_portuguese(): void
    {
        $response = $this->post('/login', []);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'O campo e-mail é obrigatório.',
            'password' => 'O campo senha é obrigatório.',
        ]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }
}
