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

    public function test_homepage_has_a_rectangular_social_preview_image(): void
    {
        $response = $this->get('/');

        $response->assertSee('property="og:image"', false);
        $response->assertSee('property="og:image:width" content="1200"', false);
        $response->assertSee('property="og:image:height" content="630"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee(asset('finansys-social.png'), false);

        $image = public_path('finansys-social.png');
        $this->assertFileExists($image);
        $dimensions = getimagesize($image);
        $this->assertIsArray($dimensions);
        $this->assertSame([1200, 630], array_slice($dimensions, 0, 2));
        $this->assertSame('image/png', $dimensions['mime']);
    }
}
