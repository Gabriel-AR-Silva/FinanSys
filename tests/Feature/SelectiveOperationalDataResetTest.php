<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelectiveOperationalDataResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_challenge_marks_every_reset_group_as_selected_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));

        $response->assertOk()
            ->assertJsonPath('groups.0.key', 'ledger_entries');

        $groupKeys = collect($response->json('groups'))->pluck('key')->values()->all();
        $defaults = $response->json('default_selected_groups');

        $this->assertSame($groupKeys, $defaults);
        $this->assertContains('categories', $defaults);
        $this->assertContains('accounts', $defaults);
        $this->assertContains('credit_cards', $defaults);
        $this->assertContains('card_operations', $defaults);
    }

    public function test_delete_everything_except_categories_preserves_categories_and_identity(): void
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

        $challenge = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));
        $selectedGroups = array_values(array_filter(
            $challenge->json('default_selected_groups'),
            fn (string $group): bool => $group !== 'categories',
        ));

        $this->actingAs($user)->delete(route('operational-data-reset.destroy'), [
            'password' => 'password',
            'confirmation_code' => (string) $challenge->json('code'),
            'slider_confirmed' => true,
            'selected_groups' => $selectedGroups,
        ])->assertRedirect()->assertSessionHas('success', 'Dados selecionados removidos. Suas categorias foram preservadas.');

        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
        $this->assertDatabaseHas('categories', ['id' => $category->getKey(), 'user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('accounts', ['id' => $account->getKey()]);
        $this->assertDatabaseMissing('credit_cards', ['id' => $card->getKey()]);
        $this->assertDatabaseMissing('ledger_entries', ['id' => $entry->getKey()]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'action' => 'purged',
        ]);
    }

    public function test_category_reset_automatically_removes_restrictive_financial_settings_dependency(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        $settingId = DB::table('monthly_financial_settings')->insertGetId([
            'user_id' => $user->getKey(),
            'month' => '2026-09',
            'protection_type' => 'fixed',
            'protection_value' => '0.00',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('essential_budgets')->insert([
            'user_id' => $user->getKey(),
            'monthly_financial_setting_id' => $settingId,
            'category_id' => $category->getKey(),
            'amount' => '150.00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $challenge = $this->actingAs($user)->postJson(route('operational-data-reset.challenge'));

        $this->actingAs($user)->delete(route('operational-data-reset.destroy'), [
            'password' => 'password',
            'confirmation_code' => (string) $challenge->json('code'),
            'slider_confirmed' => true,
            'selected_groups' => ['categories'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $category->getKey()]);
        $this->assertDatabaseMissing('monthly_financial_settings', ['id' => $settingId]);
        $this->assertDatabaseMissing('essential_budgets', ['category_id' => $category->getKey()]);
        $this->assertDatabaseHas('accounts', ['id' => $account->getKey()]);
    }
}
