<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalDataResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_challenge_returns_ten_character_code_and_preview_without_deleting_data(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->getKey(),
        ]);

        $response = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));

        $response->assertOk()
            ->assertJsonPath('counts.ledger_entries', 1)
            ->assertJsonPath('expires_in_seconds', 300);

        $code = (string) $response->json('code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $code);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_invalid_confirmation_does_not_remove_anything(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->getKey(),
        ]);

        $this->actingAs($user)->postJson(route('operational-data-reset.challenge'))->assertOk();

        $this->actingAs($user)->delete(route('operational-data-reset.destroy'), [
            'confirmation_code' => 'AAAAAAAAAA',
            'slider_confirmed' => true,
        ])->assertSessionHasErrors('confirmation_code');

        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('accounts', ['id' => $account->getKey(), 'user_id' => $user->getKey()]);
    }

    public function test_valid_reset_removes_operational_data_but_preserves_structure_and_other_users(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $card = CreditCard::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $import = BankStatementImport::query()->create([
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'institution' => 'Banco Teste',
            'institution_id' => 'test',
            'currency' => 'BRL',
            'source_account_hash' => str_repeat('a', 64),
            'source_account_suffix' => '1234',
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'status' => 'pending_review',
            'transaction_count' => 1,
        ]);
        DB::table('bank_statement_import_items')->insert([
            'bank_statement_import_id' => $import->getKey(),
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'source_index' => 0,
            'bank_type' => 'DEBIT',
            'occurred_at' => now(),
            'amount' => '10.00',
            'direction' => 'debit',
            'description' => 'Teste',
            'fingerprint' => str_repeat('b', 64),
            'dedup_key' => str_repeat('c', 64),
            'classification' => 'expense',
            'review_status' => 'pending_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $otherEntry = LedgerEntry::factory()->for($otherUser)->create([
            'reference_type' => $otherAccount->getMorphClass(),
            'reference_id' => $otherAccount->getKey(),
        ]);

        $challenge = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));
        $code = (string) $challenge->json('code');

        $this->actingAs($user)->delete(route('operational-data-reset.destroy'), [
            'confirmation_code' => $code,
            'slider_confirmed' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('ledger_entries', ['id' => $entry->getKey()]);
        $this->assertDatabaseMissing('bank_statement_imports', ['id' => $import->getKey()]);
        $this->assertDatabaseMissing('bank_statement_import_items', ['user_id' => $user->getKey()]);

        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
        $this->assertDatabaseHas('accounts', ['id' => $account->getKey(), 'user_id' => $user->getKey()]);
        $this->assertDatabaseHas('categories', ['id' => $category->getKey(), 'user_id' => $user->getKey()]);
        $this->assertDatabaseHas('credit_cards', ['id' => $card->getKey(), 'user_id' => $user->getKey()]);

        $this->assertDatabaseHas('ledger_entries', ['id' => $otherEntry->getKey(), 'user_id' => $otherUser->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'action' => 'purged',
        ]);
    }

    public function test_reset_can_be_repeated_after_new_challenge(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $challenge = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));
            $this->actingAs($user)->delete(route('operational-data-reset.destroy'), [
                'confirmation_code' => (string) $challenge->json('code'),
                'slider_confirmed' => true,
            ])->assertRedirect()->assertSessionHas('success');
        }

        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
