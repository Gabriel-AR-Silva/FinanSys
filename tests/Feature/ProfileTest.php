<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $verificationTimestamp = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->email_verified_at->equalTo($verificationTimestamp));
    }

    public function test_user_can_revoke_an_owned_trusted_device_with_the_current_password(): void
    {
        $user = User::factory()->create();
        $device = TrustedDevice::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('profile.trusted-devices.destroy', $device), [
            'password' => 'password',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Dispositivo removido da lista de confiança.');
        $this->assertModelMissing($device);
    }

    public function test_user_cannot_revoke_another_users_trusted_device(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = TrustedDevice::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->delete(route('profile.trusted-devices.destroy', $device), [
            'password' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertModelExists($device);
    }

    public function test_user_deletion_is_not_available(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($user);
        $this->assertModelExists($user);
    }
}
