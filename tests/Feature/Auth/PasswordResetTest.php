<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_reset_password_response_does_not_reveal_if_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $existingResponse = $this->post('/forgot-password', ['email' => $user->email]);
        $unknownResponse = $this->post('/forgot-password', ['email' => 'unknown@example.test']);

        $message = 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha.';
        $existingResponse->assertRedirect()->assertSessionHas('status', $message);
        $unknownResponse->assertRedirect()->assertSessionHas('status', $message);
        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_password_reset_is_limited_to_five_attempts_per_email_and_ip(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => 'same@example.test'])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'same@example.test'])->assertTooManyRequests();
    }

    public function test_password_reset_is_limited_to_twenty_attempts_per_ip(): void
    {
        Notification::fake();

        foreach (range(1, 20) as $attempt) {
            $this->post('/forgot-password', ['email' => "person{$attempt}@example.test"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'person21@example.test'])->assertTooManyRequests();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_email_is_dispatched_to_the_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Queue::assertPushed(SendQueuedNotifications::class);
    }
}
