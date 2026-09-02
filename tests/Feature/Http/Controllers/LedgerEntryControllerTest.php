<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\CreateManualLedgerEntry;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LedgerEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_users_entries_in_descending_date_and_id_order(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $older = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create(['occurred_at' => '2026-08-01']);
        $newerFirst = LedgerEntry::factory()->for($user)->for($account, 'reference')->expense()->create(['occurred_at' => '2026-08-02']);
        $newerSecond = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create(['occurred_at' => '2026-08-02']);
        LedgerEntry::factory()->for(User::factory())->create();

        $this->actingAs($user)->get(route('ledger-entries.index'))
            ->assertInertia(fn (Assert $page) => $page->component('LedgerEntries/Index')
                ->has('entries.data', 3)
                ->where('entries.data.0.id', $newerSecond->id)
                ->where('entries.data.1.id', $newerFirst->id)
                ->where('entries.data.2.id', $older->id));
    }

    #[DataProvider('periods')]
    public function test_index_applies_each_allowed_period_filter(string $period, int $expectedCount): void
    {
        $this->travelTo('2026-09-01 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        foreach ([0, 7, 15, 30, 60, 365] as $daysAgo) {
            LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create(['occurred_at' => now()->subDays($daysAgo)]);
        }

        $this->actingAs($user)->get(route('ledger-entries.index', ['period' => $period]))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', $expectedCount)->where('filters.period', $period));
    }

    public static function periods(): array
    {
        return ['all' => ['all', 6], '7 days' => ['7', 1], '15 days' => ['15', 2], '30 days' => ['30', 3], '60 days' => ['60', 4], '365 days' => ['365', 5]];
    }

    public function test_index_filters_by_type_and_owned_account_without_exposing_another_user(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create();
        LedgerEntry::factory()->for($user)->for($account, 'reference')->expense()->create();
        LedgerEntry::factory()->for($user)->for($otherAccount, 'reference')->income()->create();

        $this->actingAs($user)->get(route('ledger-entries.index', ['type' => 'income', 'account_id' => $account->id]))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.type', 'income'));
    }

    public function test_index_exposes_only_the_authenticated_users_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Moto', 'type' => 'expense']);
        Category::factory()->for($user)->create(['name' => 'Salário', 'type' => 'income']);
        Category::factory()->for(User::factory())->create(['name' => 'Categoria alheia', 'type' => 'expense']);

        $this->actingAs($user)->get(route('ledger-entries.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 2)
                ->where('categories.0.name', 'Moto')
                ->where('categories.0.type', 'expense')
                ->where('categories.1.name', 'Salário')
                ->where('categories.1.type', 'income'));
    }

    public function test_index_paginates_fifteen_entries_and_preserves_filters_in_next_page_url(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->count(16)->for($user)->for($account, 'reference')->income()->create(['occurred_at' => now()]);

        $this->actingAs($user)->get(route('ledger-entries.index', ['type' => 'income', 'account_id' => $account->id, 'period' => '30']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 15)
                ->where('entries.last_page', 2)
                ->where('entries.per_page', 15)
                ->where('entries.next_page_url', fn (string $url): bool => str_contains($url, 'type=income') && str_contains($url, 'account_id='.$account->id) && str_contains($url, 'period=30')));
    }

    public function test_valid_request_creates_and_audits_a_manual_entry_without_floating_point_conversion(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $response = $this->actingAs($user)->post(route('ledger-entries.store'), [
            'type' => 'expense', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '99999999999999999.99',
            'occurred_at' => '2026-09-01', 'description' => 'Compra importante',
            'operation_id' => '4b264db5-2755-40b6-99d9-aa48062e27b2',
        ]);

        $response->assertRedirect(route('ledger-entries.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('ledger_entries', ['user_id' => $user->id, 'category_id' => $category->id, 'reference_id' => $account->id, 'amount' => '99999999999999999.99', 'type' => 'expense']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_same_operation_is_idempotent_and_different_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $payload = ['type' => 'income', 'account_id' => $account->id, 'category_id' => $incomeCategory->id, 'amount' => '10.20', 'occurred_at' => '2026-09-01', 'description' => 'Receita', 'operation_id' => '852f3485-e9e7-442c-81b8-d49415b49f07'];

        $this->actingAs($user)->post(route('ledger-entries.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('ledger-entries.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $this->actingAs($user)->post(route('ledger-entries.store'), [...$payload, 'amount' => '10.21'])
            ->assertSessionHasErrors(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);

        $this->actingAs($user)->post(route('ledger-entries.store'), [...$payload, 'type' => 'expense', 'category_id' => $expenseCategory->id])
            ->assertSessionHasErrors(['operation_id']);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_invalid_manual_entry_is_rejected_with_visible_messages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('ledger-entries.store'), [
            'type' => 'transfer_in', 'account_id' => 999, 'category_id' => 999, 'amount' => '0.001', 'occurred_at' => now()->addDay()->toDateString(), 'description' => '', 'operation_id' => 'invalid',
        ])->assertSessionHasErrors(['type', 'account_id', 'category_id', 'amount', 'occurred_at', 'operation_id']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_amount_beyond_the_database_decimal_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        $this->actingAs($user)->post(route('ledger-entries.store'), [
            'type' => 'income', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '100000000000000000.00',
            'occurred_at' => now()->toDateString(), 'description' => 'Acima do limite', 'operation_id' => fake()->uuid(),
        ])->assertSessionHasErrors(['amount' => 'O valor ultrapassa o limite suportado.']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_user_cannot_create_in_another_users_account(): void
    {
        $attacker = User::factory()->create();
        $account = Account::factory()->for(User::factory())->create();
        $category = Category::factory()->for($attacker)->create(['type' => 'income']);

        $this->actingAs($attacker)->post(route('ledger-entries.store'), [
            'type' => 'income', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '10.00', 'occurred_at' => now()->toDateString(), 'description' => 'Tentativa', 'operation_id' => fake()->uuid(),
        ])->assertSessionHasErrors(['account_id' => 'A conta selecionada não está disponível.']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_manual_entry_rejects_foreign_or_incompatible_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $foreignCategory = Category::factory()->for(User::factory())->create(['type' => 'expense']);
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $payload = [
            'type' => 'expense', 'account_id' => $account->id, 'amount' => '10.00',
            'occurred_at' => now()->toDateString(), 'description' => null, 'operation_id' => fake()->uuid(),
        ];

        $this->actingAs($user)->post(route('ledger-entries.store'), [...$payload, 'category_id' => $foreignCategory->id])
            ->assertSessionHasErrors(['category_id' => 'A categoria selecionada não está disponível para este tipo de lançamento.']);
        $this->actingAs($user)->post(route('ledger-entries.store'), [...$payload, 'category_id' => $incomeCategory->id, 'operation_id' => fake()->uuid()])
            ->assertSessionHasErrors(['category_id' => 'A categoria selecionada não está disponível para este tipo de lançamento.']);

        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_index_filters_by_an_owned_active_or_inactive_category_and_rejects_a_foreign_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $selected = Category::factory()->for($user)->create(['name' => 'Moradia', 'type' => 'expense', 'status' => RecordStatus::Inactive]);
        $other = Category::factory()->for($user)->create(['type' => 'expense']);
        $matching = LedgerEntry::factory()->for($user)->for($account, 'reference')->for($selected)->expense()->create();
        LedgerEntry::factory()->for($user)->for($account, 'reference')->for($other)->expense()->create();

        $this->actingAs($user)->get(route('ledger-entries.index', ['category_id' => $selected->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.id', $matching->id)
                ->where('filters.category_id', $selected->id));

        $foreign = Category::factory()->for(User::factory())->create();
        $this->actingAs($user)->get(route('ledger-entries.index', ['category_id' => $foreign->id]))
            ->assertSessionHasErrors(['category_id' => 'A categoria selecionada não está disponível.']);
    }

    public function test_manual_entry_rejects_an_inactive_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense', 'status' => RecordStatus::Inactive]);

        $this->actingAs($user)->post(route('ledger-entries.store'), [
            'type' => 'expense', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '10.00',
            'occurred_at' => now()->toDateString(), 'description' => null, 'operation_id' => fake()->uuid(),
        ])->assertSessionHasErrors(['category_id' => 'A categoria selecionada não está disponível para este tipo de lançamento.']);

        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('unavailableAccountStates')]
    public function test_manual_entry_cannot_be_created_in_an_unavailable_account(string $state): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        if ($state === 'inactive') {
            $account->update(['status' => RecordStatus::Inactive]);
        } else {
            $account->delete();
        }

        $this->actingAs($user)->post(route('ledger-entries.store'), [
            'type' => 'income', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '10.00', 'occurred_at' => now()->toDateString(), 'description' => 'Receita', 'operation_id' => fake()->uuid(),
        ])->assertSessionHasErrors('account_id');
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public static function unavailableAccountStates(): array
    {
        return ['inactive' => ['inactive'], 'deleted' => ['deleted']];
    }

    #[DataProvider('invalidActionPayloads')]
    public function test_public_action_rejects_invalid_domain_fields(LedgerEntryType $type, string $amount, string $date, string $description, string $errorKey): void
    {
        $this->travelTo('2026-09-01 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        try {
            app(CreateManualLedgerEntry::class)->handle($user, $account->id, $category->id, $type, $amount, $date, $description, fake()->uuid());
            $this->fail('A Action deveria rejeitar os dados adulterados.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public static function invalidActionPayloads(): array
    {
        return [
            'protected type' => [LedgerEntryType::OpeningBalance, '10.00', '2026-09-01', 'Saldo', 'type'],
            'long description' => [LedgerEntryType::Income, '10.00', '2026-09-01', str_repeat('a', 256), 'description'],
            'future date' => [LedgerEntryType::Expense, '10.00', '2026-09-02', 'Compra', 'occurred_at'],
            'invalid date' => [LedgerEntryType::Expense, '10.00', '2026-02-31', 'Compra', 'occurred_at'],
            'fraction overflow' => [LedgerEntryType::Income, '10.001', '2026-09-01', 'Receita', 'amount'],
            'decimal overflow' => [LedgerEntryType::Income, '100000000000000000.00', '2026-09-01', 'Receita', 'amount'],
        ];
    }

    public function test_public_action_rejects_an_inactive_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['status' => RecordStatus::Inactive]);
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        try {
            app(CreateManualLedgerEntry::class)->handle($user, $account->id, $category->id, LedgerEntryType::Income, '10.00', now()->toDateString(), 'Receita', fake()->uuid());
            $this->fail('A Action deveria rejeitar a conta inativa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account_id', $exception->errors());
        }
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_public_action_rejects_an_invalid_operation_uuid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        try {
            app(CreateManualLedgerEntry::class)->handle($user, $account->id, $category->id, LedgerEntryType::Income, '10.00', now()->toDateString(), 'Receita', 'not-a-uuid');
            $this->fail('A Action deveria rejeitar a chave de operação inválida.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Informe uma chave de operação UUID válida.'], $exception->errors()['operation_id']);
        }
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_manual_entry_can_be_deleted_and_restored_within_thirty_days(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create();

        $this->actingAs($user)->delete(route('ledger-entries.destroy', $entry))->assertRedirect();
        $this->assertSoftDeleted($entry);
        $this->assertNull($entry->refresh()->deletion_batch_id);

        $this->actingAs($user)->post(route('ledger-entries.restore', $entry))->assertRedirect();
        $this->assertNotSoftDeleted($entry);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_transfer_and_another_users_entry_cannot_be_deleted_individually(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transfer = LedgerEntry::factory()->for($user)->for($account, 'reference')->create(['type' => LedgerEntryType::TransferOut]);
        $foreign = LedgerEntry::factory()->create();

        $this->actingAs($user)->delete(route('ledger-entries.destroy', $transfer))->assertNotFound();
        $this->actingAs($user)->delete(route('ledger-entries.destroy', $foreign))->assertNotFound();
        $this->assertNotSoftDeleted($transfer);
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_expired_or_cascade_deleted_entry_cannot_be_restored_individually(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expired = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create();
        $cascade = LedgerEntry::factory()->for($user)->for($account, 'reference')->expense()->create();
        $expired->delete();
        $cascade->update(['deletion_batch_id' => fake()->uuid()]);
        $cascade->delete();
        DB::table('ledger_entries')->where('id', $expired->id)->update(['deleted_at' => now()->subDays(30)->subMicrosecond()->format('Y-m-d H:i:s.u')]);

        $this->actingAs($user)->post(route('ledger-entries.restore', $expired))->assertSessionHasErrors('ledger_entry');
        $this->actingAs($user)->post(route('ledger-entries.restore', $cascade))->assertNotFound();
        $this->assertSoftDeleted($expired);
        $this->assertSoftDeleted($cascade);
    }

    public function test_restorable_list_omits_entry_when_its_account_is_inactive(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create();
        $entry->delete();
        $account->update(['status' => RecordStatus::Inactive]);

        $this->actingAs($user)->get(route('ledger-entries.index'))
            ->assertInertia(fn (Assert $page) => $page->has('deletedEntries', 0));
    }

    public function test_user_cannot_restore_another_users_entry(): void
    {
        $owner = User::factory()->create();
        $entry = LedgerEntry::factory()->for($owner)->for(Account::factory()->for($owner), 'reference')->income()->create();
        $entry->delete();

        $this->actingAs(User::factory()->create())->post(route('ledger-entries.restore', $entry))->assertNotFound();
        $this->assertSoftDeleted($entry);
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $this->get(route('ledger-entries.index'))->assertRedirect(route('login'));
        $this->post(route('ledger-entries.store'))->assertRedirect(route('login'));
        $this->delete(route('ledger-entries.destroy', 1))->assertRedirect(route('login'));
        $this->post(route('ledger-entries.restore', 1))->assertRedirect(route('login'));
    }
}
