<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_defensive_browser_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'")
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_authenticated_financial_responses_cannot_be_cached_by_the_browser(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_https_responses_enable_transport_security(): void
    {
        $response = $this->withServerVariables(['HTTPS' => 'on'])->get(route('home'));

        $response->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
