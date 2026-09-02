<?php

namespace Tests\Feature;

use App\Actions\DeletePocket;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PocketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_owned_pockets_and_separates_account_and_pocket_balances(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create(['name' => 'Reserva']);
        Pocket::factory()->create();
        $account->ledgerEntries()->create(['user_id' => $user->id, 'type' => LedgerEntryType::Income, 'amount' => '100.00', 'operation_id' => fake()->uuid(), 'occurred_at' => now()]);
        $pocket->ledgerEntries()->create(['user_id' => $user->id, 'type' => LedgerEntryType::TransferIn, 'amount' => '25.00', 'operation_id' => fake()->uuid(), 'occurred_at' => now()]);

        $response = $this->actingAs($user)->get(route('pockets.index'));
        $response->assertInertia(fn (Assert $page) => $page->component('Pockets/Index')->has('pockets', 1)->where('pockets.0.balance', 25)->where('accounts.0.available_balance', 100));
        $this->actingAs($user)->get(route('accounts.index'))->assertInertia(fn (Assert $page) => $page->where('accounts.0.available_balance', 100)->where('accounts.0.pockets_total', 25));
    }

    public function test_crud_uses_allowlisted_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('pockets.store'), ['account_id' => $account->id, 'name' => 'Viagem', 'status' => 'blocked', 'operation_id' => fake()->uuid()])->assertRedirect(route('pockets.index'));
        $pocket = Pocket::firstOrFail();
        $this->assertSame('active', $pocket->status->value);
        $this->actingAs($user)->patch(route('pockets.update', $pocket), ['name' => 'Férias', 'account_id' => 999])->assertRedirect();
        $this->assertSame('Férias', $pocket->refresh()->name);
        $this->assertSame($account->id, $pocket->account_id);
    }

    public function test_cross_user_changes_are_hidden_and_guests_redirect_to_login(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $pocket = Pocket::factory()->for($owner)->for($account)->create();

        $this->actingAs($attacker)->patch(route('pockets.update', $pocket), ['name' => 'Invadida'])->assertNotFound();
        $this->actingAs($attacker)->delete(route('pockets.destroy', $pocket))->assertNotFound();
        $this->app['auth']->guard()->logout();
        $this->post(route('pockets.store'), [])->assertRedirect(route('login'));
    }

    public function test_store_rejects_foreign_inactive_and_deleted_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Account::factory()->for($other)->create();
        $inactive = Account::factory()->for($user)->create(['status' => 'inactive']);
        $deleted = Account::factory()->for($user)->create();
        $deleted->delete();

        foreach ([$foreign, $inactive, $deleted] as $account) {
            $this->actingAs($user)->post(route('pockets.store'), ['account_id' => $account->id, 'name' => 'Reserva', 'operation_id' => fake()->uuid()])->assertNotFound();
        }

        $this->assertDatabaseCount('pockets', 0);
    }

    public function test_restore_http_boundaries_and_success_flash(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $pocket = Pocket::factory()->for($owner)->for($account)->create();
        app(DeletePocket::class)->handle($owner, $pocket->id);

        $this->post(route('pockets.restore', $pocket))->assertRedirect(route('login'));
        $this->actingAs($attacker)->post(route('pockets.restore', $pocket))->assertNotFound();
        $this->assertSoftDeleted($pocket);
        $this->actingAs($owner)->post(route('pockets.restore', $pocket))->assertRedirect(route('pockets.index'))->assertSessionHas('success', 'Caixinha restaurada com sucesso.');
        $this->assertNotSoftDeleted($pocket);
    }

    public function test_expired_restore_returns_validation_error_without_mutation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        app(DeletePocket::class)->handle($user, $pocket->id);
        DB::table('pockets')->where('id', $pocket->id)->update(['deleted_at' => now()->subDays(30)->subMicrosecond()->format('Y-m-d H:i:s.u')]);

        $this->actingAs($user)->post(route('pockets.restore', $pocket))->assertSessionHasErrors(['pocket' => 'O prazo de restauração expirou.']);

        $this->assertSoftDeleted($pocket);
    }
}
