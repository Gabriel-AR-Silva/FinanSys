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

    public function test_shared_report_control_uses_server_permission_and_token_scoped_route(): void
    {
        $dashboard = file_get_contents(resource_path('js/Pages/Nfc/Index.vue'));

        $this->assertStringContainsString('v-if="shared && can_export_report"', $dashboard);
        $this->assertStringContainsString("nfcRoute('nfc.report.export'", $dashboard);
        $this->assertStringContainsString("dialog.kind === 'report' && can_export_report", $dashboard);
        $this->assertStringContainsString("dialog.kind === 'reset' && !shared && authenticatedUser", $dashboard);
        $this->assertStringContainsString("dialog.kind === 'share' && !shared && authenticatedUser", $dashboard);
    }

    public function test_dashboard_separates_finance_evolution_and_registry_without_unmounting_state(): void
    {
        $dashboard = file_get_contents(resource_path('js/Pages/Nfc/Index.vue'));

        foreach (['finance', 'evolution', 'registry'] as $tab) {
            $this->assertStringContainsString('id="nfc-dashboard-panel-'.$tab.'"', $dashboard);
            $this->assertStringContainsString(sprintf('v-show="activeDashboardTab === \'%s\'"', $tab), $dashboard);
        }

        $this->assertStringContainsString('onDashboardTabKeydown', $dashboard);
        $this->assertStringContainsString('nfc-finance-strip', $dashboard);
        $this->assertStringContainsString('nfc-metric-table', $dashboard);
        $this->assertStringContainsString('scope="col"', $dashboard);
    }
}
