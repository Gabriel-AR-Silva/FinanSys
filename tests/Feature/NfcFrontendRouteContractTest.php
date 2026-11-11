<?php

namespace Tests\Feature;

use Tests\TestCase;

class NfcFrontendRouteContractTest extends TestCase
{
    public function test_injected_nfc_routes_are_referenced_through_props_in_child_components(): void
    {
        foreach (['Editor.vue', 'Notices.vue'] as $component) {
            $path = resource_path('js/Pages/Nfc/'.$component);
            $source = file_get_contents($path);

            $this->assertNotFalse($source, 'Missing NFC component: '.$component);
            $this->assertStringContainsString('props.nfcRoute(', $source, $component);
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\\w.])nfcRoute\\s*\\(/',
                $source,
                'Unqualified NFC route helper in '.$component.' can raise ReferenceError at runtime.',
            );
        }
    }

    public function test_nfc_write_components_show_failures_and_update_after_success(): void
    {
        foreach (['Editor.vue', 'Notices.vue'] as $component) {
            $source = file_get_contents(resource_path('js/Pages/Nfc/'.$component));

            $this->assertStringContainsString('onSuccess:', $source, $component);
            $this->assertStringContainsString('onError:', $source, $component);
        }

        $dashboard = file_get_contents(resource_path('js/Pages/Nfc/Index.vue'));

        $this->assertStringContainsString('@feedback="notify"', $dashboard);
        $this->assertStringContainsString('onSuccess:', $dashboard);
        $this->assertStringContainsString('onError:', $dashboard);
    }
}
