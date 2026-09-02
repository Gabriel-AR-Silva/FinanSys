<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerEntryReversalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_owner_can_reverse_an_entry_and_receives_success_feedback(): void
    {
        $user = User::factory()->create();
        $entry = LedgerEntry::factory()->for($user)->for(Account::factory()->for($user), 'reference')->income()->create();

        $this->actingAs($user)->post(route('ledger-entries.reversals.store', $entry), [
            'operation_id' => '6f0d267f-a1ac-416f-b045-75879046396e',
        ])->assertRedirect(route('ledger-entries.index'))
            ->assertSessionHas('success', 'Lançamento estornado com sucesso.');

        $this->assertDatabaseHas('ledger_entries', ['reversal_of_operation_id' => $entry->operation_id]);
    }

    public function test_endpoint_validates_uuid_authentication_and_ownership(): void
    {
        $owner = User::factory()->create();
        $entry = LedgerEntry::factory()->for($owner)->income()->create();

        $this->post(route('ledger-entries.reversals.store', $entry), ['operation_id' => fake()->uuid()])
            ->assertRedirect(route('login'));
        $this->actingAs($owner)->post(route('ledger-entries.reversals.store', $entry), ['operation_id' => 'invalid'])
            ->assertSessionHasErrors('operation_id');
        $this->actingAs(User::factory()->create())->post(route('ledger-entries.reversals.store', $entry), ['operation_id' => fake()->uuid()])
            ->assertNotFound();

        $this->assertDatabaseMissing('ledger_entries', ['reversal_of_operation_id' => $entry->operation_id]);
    }

    public function test_reversal_chain_cannot_be_deleted_individually(): void
    {
        $user = User::factory()->create();
        $original = LedgerEntry::factory()->for($user)->for(Account::factory()->for($user), 'reference')->income()->create();
        $this->actingAs($user)->post(route('ledger-entries.reversals.store', $original), ['operation_id' => fake()->uuid()]);
        $reversal = LedgerEntry::query()->where('reversal_of_operation_id', $original->operation_id)->sole();

        $this->actingAs($user)->delete(route('ledger-entries.destroy', $original))->assertNotFound();
        $this->actingAs($user)->delete(route('ledger-entries.destroy', $reversal))->assertNotFound();

        $this->assertDatabaseCount('ledger_entries', 2);
    }
}
