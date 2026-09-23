<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $verificationTimestamp = $user->email_verified_at;

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->email_verified_at->equalTo($verificationTimestamp));
    }

    public function test_avatar_can_be_uploaded_and_served_only_to_its_owner(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+tmZkAAAAASUVORK5CYII=');

        $this->actingAs($user)->post('/profile', [
            '_method' => 'patch',
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png),
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->actingAs($user)->get(route('profile.avatar'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($other)->get(route('profile.avatar'))->assertNotFound();
        $this->get('/profile/avatar')->assertNotFound();
    }

    public function test_avatar_route_returns_not_found_when_file_is_missing_instead_of_server_error(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/missing.png']);

        $this->actingAs($user)->get(route('profile.avatar'))->assertNotFound();
    }

    public function test_removing_avatar_keeps_user_and_removes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/old.png']);
        Storage::disk('public')->put('avatars/old.png', 'old content');

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing('avatars/old.png');
    }

    public function test_user_deletion_is_not_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile', [
            'password' => 'password',
        ])->assertMethodNotAllowed();

        $this->assertAuthenticatedAs($user);
        $this->assertModelExists($user);
    }
}
