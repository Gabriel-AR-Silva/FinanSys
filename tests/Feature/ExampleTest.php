<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canLogin', true)
            ->missing('canRegister')
            ->missing('laravelVersion')
            ->missing('phpVersion'));
    }

    public function test_homepage_exposes_finansys_and_tsuki_brand_assets(): void
    {
        $response = $this->get('/');

        $response->assertSee('finansys-icon.svg', false);
        $response->assertSee('finansys-32.png', false);
        $response->assertSee('apple-touch-icon.png', false);
        $response->assertSee('site.webmanifest', false);

        foreach (['finansys-icon.svg', 'tsuki-mark.svg', 'finansys-32.png', 'finansys-192.png', 'apple-touch-icon.png', 'site.webmanifest'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }
}
