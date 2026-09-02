<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_seeded_user_can_access_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_email_verification_routes_are_not_exposed(): void
    {
        $this->get('/verify-email')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
