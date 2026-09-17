<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_favicon_is_publicly_accessible_as_png(): void
    {
        $this->get(route('brand.favicon'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_social_preview_is_publicly_accessible_as_png(): void
    {
        $this->get(route('brand.social'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
