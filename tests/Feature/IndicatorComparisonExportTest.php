<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorComparisonExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_financial_data(): void
    {
        $this->get('/exportacoes/indicadores.json')->assertRedirect(route('login'));
    }

    public function test_export_contains_only_authenticated_users_records_and_no_descriptions(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = LedgerEntry::factory()->for($user)->income()->create([
            'amount' => '125.50', 'occurred_at' => '2026-09-20 12:00:00',
            'description' => 'SEGREDO PRIVADO NA DESCRICAO',
        ]);
        LedgerEntry::factory()->for($other)->income()->create([
            'amount' => '999.00', 'occurred_at' => '2026-09-20 12:00:00',
            'description' => 'OUTRO USUARIO',
        ]);

        $response = $this->actingAs($user)->get(route('indicator-comparison.export'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store, max-age=0')
            ->assertJsonPath('schema', 'finansys-indicator-comparison-v1')
            ->assertJsonPath('complete', true)
            ->assertJsonPath('ledger_entries.0.id', $own->id)
            ->assertJsonPath('ledger_entries.0.amount', '125.50')
            ->assertJsonCount(1, 'ledger_entries');
        $this->assertStringNotContainsString('SEGREDO PRIVADO', $response->getContent());
        $this->assertStringNotContainsString('OUTRO USUARIO', $response->getContent());
        $this->assertStringNotContainsString('999.00', $response->getContent());
        $this->assertStringNotContainsString($user->email, $response->getContent());
    }

    public function test_export_refuses_more_than_one_hundred_entries_instead_of_silently_truncating(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        LedgerEntry::factory()->for($user)->income()->count(101)->create([
            'amount' => '1.00', 'occurred_at' => '2026-09-20 12:00:00',
        ]);

        $this->actingAs($user)->get(route('indicator-comparison.export'))
            ->assertUnprocessable()
            ->assertJsonMissingPath('ledger_entries')
            ->assertJsonPath('message', 'Exportação não gerada: há pelo menos 101 lançamentos no intervalo (limite de 100). Nenhum conjunto parcial foi exportado.');
    }

    public function test_export_reports_opening_balance_separately_from_period_entries(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        LedgerEntry::factory()->for($user)->openingBalance()->create([
            'amount' => '300.00', 'occurred_at' => '2026-08-01 12:00:00',
        ]);
        LedgerEntry::factory()->for($user)->expense()->create([
            'amount' => '20.00', 'occurred_at' => '2026-09-20 12:00:00',
        ]);

        $this->actingAs($user)->get(route('indicator-comparison.export'))
            ->assertOk()->assertJsonPath('opening_balance_before_range', '300.00')
            ->assertJsonCount(1, 'ledger_entries')
            ->assertJsonPath('reported_indicators.general_balance', '280.00');
    }
}
