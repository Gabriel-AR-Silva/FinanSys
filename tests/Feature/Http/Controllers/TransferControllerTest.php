<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('transfers.store'))->assertRedirect(route('login'));
    }

    public function test_valid_request_transfers_balance_and_flashes_success(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '100.00']);

        $response = $this->actingAs($user)->post(route('transfers.store'), [
            'source_type' => 'account',
            'source_id' => $source->id,
            'destination_type' => 'account',
            'destination_id' => $destination->id,
            'amount' => '40.00',
            'operation_id' => '3deeff3f-d22f-4c7a-a666-ed8080572403',
        ]);

        $response->assertRedirect(route('ledger-entries.index'))
            ->assertSessionHas('success', 'Transferência realizada com sucesso.');
        $this->assertDatabaseHas('ledger_entries', ['reference_id' => $source->id, 'type' => 'transfer_out', 'amount' => 40]);
        $this->assertDatabaseHas('ledger_entries', ['reference_id' => $destination->id, 'type' => 'transfer_in', 'amount' => 40]);
    }

    public function test_another_users_reference_is_rejected_without_writes(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for(User::factory())->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '100.00']);

        $response = $this->actingAs($user)->from(route('ledger-entries.index'))->post(route('transfers.store'), [
            'source_type' => 'account',
            'source_id' => $source->id,
            'destination_type' => 'account',
            'destination_id' => $destination->id,
            'amount' => '40.00',
            'operation_id' => 'c18f0312-a65b-4a98-ab81-30b7bc5e9f48',
        ]);

        $response->assertRedirect(route('ledger-entries.index'))
            ->assertSessionHasErrors(['destination_id' => 'A referência selecionada não está disponível.']);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_payload_is_rejected_with_visible_messages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('ledger-entries.index'))->post(route('transfers.store'), [
            'source_type' => 'invalid',
            'source_id' => 'x',
            'destination_type' => 'invalid',
            'destination_id' => 'x',
            'amount' => '0.001',
            'operation_id' => 'invalid',
        ]);

        $response->assertRedirect(route('ledger-entries.index'))
            ->assertSessionHasErrors([
                'source_type' => 'Escolha uma origem válida.',
                'destination_type' => 'Escolha um destino válido.',
                'amount' => 'Informe um valor com no máximo duas casas decimais.',
            ])
            ->assertSessionHasErrors(['source_id', 'destination_id', 'operation_id']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_insufficient_balance_and_same_reference_are_rejected_without_writes(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '10.00']);
        $basePayload = [
            'source_type' => 'account', 'source_id' => $source->id,
            'destination_type' => 'account', 'destination_id' => $destination->id,
            'amount' => '10.01', 'operation_id' => fake()->uuid(),
        ];

        $this->actingAs($user)->from(route('ledger-entries.index'))
            ->post(route('transfers.store'), $basePayload)
            ->assertSessionHasErrors(['amount' => 'O saldo da origem é insuficiente para esta transferência.']);

        $this->actingAs($user)->from(route('ledger-entries.index'))
            ->post(route('transfers.store'), array_merge($basePayload, ['destination_id' => $source->id, 'amount' => '5.00', 'operation_id' => fake()->uuid()]))
            ->assertSessionHasErrors(['destination_id' => 'A origem e o destino devem ser diferentes.']);

        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_replayed_request_does_not_duplicate_the_transfer(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '100.00']);
        $payload = [
            'source_type' => 'account', 'source_id' => $source->id,
            'destination_type' => 'account', 'destination_id' => $destination->id,
            'amount' => '40.00', 'operation_id' => '5883fb75-027c-49e1-9881-e3a6edbc4e19',
        ];

        $this->actingAs($user)->post(route('transfers.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('transfers.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertDatabaseCount('audit_logs', 2);
    }
}
