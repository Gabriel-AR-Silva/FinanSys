<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_accounts_with_derived_balances(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::query()->create(['user_id' => $user->id, 'name' => 'Principal']);
        Account::query()->create(['user_id' => $otherUser->id, 'name' => 'Conta alheia']);
        $account->ledgerEntries()->createMany([
            ['user_id' => $user->id, 'type' => LedgerEntryType::Income, 'amount' => '150.00', 'operation_id' => fake()->uuid(), 'occurred_at' => now()],
            ['user_id' => $user->id, 'type' => LedgerEntryType::Expense, 'amount' => '40.00', 'operation_id' => fake()->uuid(), 'occurred_at' => now()],
        ]);

        $response = $this->actingAs($user)->get(route('accounts.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Index')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Principal')
            ->where('accounts.0.available_balance', 110)
            ->where('accounts.0.pockets_total', 0));
    }

    public function test_valid_request_creates_an_account_and_opening_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Banco principal',
            'opening_balance' => '1250.50',
            'operation_id' => '3203c8f8-2d58-4ee7-bc25-e48425aaf889',
        ]);

        $response->assertRedirect(route('accounts.index'))->assertSessionHas('success', 'Conta criada com sucesso.');
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'name' => 'Banco principal']);
        $this->assertDatabaseHas('ledger_entries', ['user_id' => $user->id, 'type' => LedgerEntryType::OpeningBalance->value, 'amount' => 1250.50]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_negative_opening_balance_is_rejected_with_a_visible_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Inválida',
            'opening_balance' => '-0.01',
        ]);

        $response->assertSessionHasErrors(['opening_balance' => 'O saldo inicial deve ser maior ou igual a zero.']);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_user_can_rename_delete_and_restore_an_owned_account(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create(['user_id' => $user->id, 'name' => 'Antiga']);

        $this->actingAs($user)->patch(route('accounts.update', $account), ['name' => 'Nova'])
            ->assertRedirect(route('accounts.index'));
        $this->assertSame('Nova', $account->refresh()->name);

        $this->actingAs($user)->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));
        $this->assertSoftDeleted($account);

        $this->actingAs($user)->post(route('accounts.restore', $account))
            ->assertRedirect(route('accounts.index'));
        $this->assertNotSoftDeleted($account);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_user_cannot_change_another_users_account(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::query()->create(['user_id' => $owner->id, 'name' => 'Privada']);

        $this->actingAs($attacker)->patch(route('accounts.update', $account), ['name' => 'Invadida'])
            ->assertNotFound();
        $this->actingAs($attacker)->delete(route('accounts.destroy', $account))
            ->assertNotFound();

        $this->assertSame('Privada', $account->refresh()->name);
        $this->assertFalse($account->trashed());
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->post(route('accounts.store'), ['name' => 'Conta', 'opening_balance' => '0'])
            ->assertRedirect(route('login'));
    }

    public function test_index_hides_accounts_whose_restoration_period_expired(): void
    {
        $this->travelTo(now()->setMicrosecond(456789));
        $user = User::factory()->create();
        $available = Account::factory()->for($user)->create();
        $expired = Account::factory()->for($user)->create();
        $available->delete();
        $expired->delete();
        $restorationThreshold = now()->subDays(30);
        DB::table('accounts')->where('id', $available->id)->update([
            'deleted_at' => $restorationThreshold->format('Y-m-d H:i:s.u'),
        ]);
        DB::table('accounts')->where('id', $expired->id)->update([
            'deleted_at' => $restorationThreshold->subMicrosecond()->format('Y-m-d H:i:s.u'),
        ]);

        $this->actingAs($user)->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('deletedAccounts', 1)
                ->where('deletedAccounts.0.id', $available->id));
    }
}
