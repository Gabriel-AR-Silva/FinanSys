<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\PatrimonialAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDiagnosticExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_financial_diagnostic_export(): void
    {
        $this->get(route('financial-diagnostic-export.show'))
            ->assertRedirect(route('login'));
    }

    public function test_export_contains_only_authenticated_users_financial_domain_and_no_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'secret-password-for-test',
        ]);
        $account = Account::factory()->for($user)->create(['name' => 'Conta principal']);
        LedgerEntry::factory()->for($user)->for($account, 'reference')->create([
            'type' => LedgerEntryType::Income,
            'amount' => '123.45',
            'description' => 'Receita para validar exportação',
        ]);
        CreditCard::factory()->for($user)->create([
            'name' => 'Cartão diagnóstico',
            'credit_limit' => '500.00',
        ]);
        PatrimonialAsset::query()->create([
            'user_id' => $user->id,
            'name' => 'Moto diagnóstico',
            'estimated_value' => '18000.00',
            'debt_balance' => '11000.00',
            'valued_on' => '2026-09-23',
        ]);

        $other = User::factory()->create();
        $otherAccount = Account::factory()->for($other)->create(['name' => 'Conta alheia']);
        LedgerEntry::factory()->for($other)->for($otherAccount, 'reference')->create([
            'type' => LedgerEntryType::Income,
            'amount' => '9999.99',
            'description' => 'Não exportar',
        ]);

        $response = $this->actingAs($user)->get(route('financial-diagnostic-export.show'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('meta.schema', 'finansys-financial-diagnostic-export')
            ->assertJsonPath('meta.version', 1)
            ->assertJsonPath('meta.contains_credentials', false)
            ->assertJsonPath('raw.accounts.0.name', 'Conta principal')
            ->assertJsonPath('raw.patrimonial_assets.0.name', 'Moto diagnóstico')
            ->assertJsonPath('derived.card_limits.0.total', '500.00');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $payload = $response->json();
        $this->assertSame('123.45', number_format((float) $payload['raw']['ledger_entries'][0]['amount'], 2, '.', ''));

        $this->assertArrayNotHasKey('users', $payload['raw']);
        $this->assertArrayNotHasKey('social_identities', $payload['raw']);
        $this->assertStringNotContainsString('owner@example.test', $response->getContent());
        $this->assertStringNotContainsString('secret-password-for-test', $response->getContent());
        $this->assertStringNotContainsString('Conta alheia', $response->getContent());
        $this->assertStringNotContainsString('Não exportar', $response->getContent());

        foreach ($payload['raw'] as $rows) {
            foreach ($rows as $row) {
                $this->assertArrayNotHasKey('user_id', $row);
                $this->assertArrayNotHasKey('password', $row);
                $this->assertArrayNotHasKey('remember_token', $row);
            }
        }
    }
}
