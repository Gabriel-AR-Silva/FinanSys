<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeatureAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ofx_routes_are_not_reachable_when_feature_is_disabled(): void
    {
        config()->set('features.ofx', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ofx-imports.index'))
            ->assertNotFound();
    }

    public function test_ofx_route_is_available_when_feature_is_enabled(): void
    {
        config()->set('features.ofx', true);

        $user = User::factory()->create();
        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('ofx-imports.index'))
            ->assertOk();
    }

    public function test_feature_flag_is_shared_with_authenticated_inertia_pages(): void
    {
        config()->set('features.ofx', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('features.ofx', false));
    }

    public function test_document_navigation_with_stale_inertia_headers_recovers_as_html(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => 'stale-version',
                'X-Requested-With' => 'XMLHttpRequest',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Dest' => 'document',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_inertia_responses_are_not_cacheable_as_documents(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->get(route('dashboard'));

        $this->assertContains($response->getStatusCode(), [200, 409]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }
}
