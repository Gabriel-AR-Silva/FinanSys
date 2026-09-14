<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\EssentialBudget;
use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FinancialSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_or_write_settings(): void
    {
        $this->get(route('financial-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('financial-settings.update'), $this->payload())->assertRedirect(route('login'));
        $this->post(route('financial-settings.categories.store'), ['month' => '2026-09', 'name' => 'Saúde'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('monthly_financial_settings', 0);
    }

    public function test_new_month_is_unconfigured_with_zero_protection_and_no_writes(): void
    {
        $user = User::factory()->create();
        $this->travelTo(new \DateTimeImmutable('2026-10-01T01:30:00+00:00'));
        Category::factory()->for($user)->create(['name' => 'Saúde', 'type' => 'expense']);
        Category::factory()->for(User::factory())->create(['name' => 'Alheia', 'type' => 'expense']);
        Category::factory()->for($user)->create(['type' => 'income']);

        $this->actingAs($user)->get(route('financial-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('FinancialSettings/Edit')
                ->where('month', '2026-09')->where('settings.configured', false)
                ->where('settings.protection_value', '0.00')->where('settings.version', 0)
                ->has('settings.essentials', 0)->has('categories', 1)->where('categories.0.name', 'Saúde'));
        $this->assertDatabaseCount('monthly_financial_settings', 0);
    }

    public function test_saves_exact_decimals_audit_and_round_trips_only_selected_month(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $other = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-08', 'protection_value' => '99.00']);
        $data = $this->payload(['protection_type' => 'percentage', 'protection_value' => '20.25',
            'essentials' => [['category_id' => $category->id, 'amount' => '600.01']]]);

        $this->actingAs($user)->put(route('financial-settings.update'), $data)
            ->assertRedirect(route('financial-settings.edit', ['month' => '2026-09']))->assertSessionHas('success');
        $setting = MonthlyFinancialSetting::where('month', '2026-09')->sole();
        $this->assertSame('20.25', $setting->protection_value);
        $this->assertSame('600.01', $setting->essentials->sole()->amount);
        $this->assertSame('99.00', $other->fresh()->protection_value);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame('20.25', AuditLog::where('auditable_type', 'monthly_financial_setting')->sole()->after['protection_value']);

        $this->get(route('financial-settings.edit', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page->where('settings.configured', true)
                ->where('settings.protection_value', '20.25')->where('settings.essentials.0.amount', '600.01'));
    }

    public function test_other_user_settings_cannot_be_read_or_overwritten_by_payload(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $original = MonthlyFinancialSetting::factory()->for($owner)->create(['protection_value' => '90.00']);

        $this->actingAs($other)->get(route('financial-settings.edit', ['month' => '2026-09', 'user_id' => $owner->id]))
            ->assertInertia(fn (Assert $page) => $page->where('settings.configured', false));
        $this->put(route('financial-settings.update'), $this->payload(['user_id' => $owner->id, 'id' => $original->id]))
            ->assertRedirect();
        $this->assertSame('90.00', $original->fresh()->protection_value);
        $this->assertDatabaseHas('monthly_financial_settings', ['user_id' => $other->id, 'month' => '2026-09']);
    }

    public function test_foreign_income_and_inactive_categories_are_rejected_atomically(): void
    {
        $user = User::factory()->create();
        $categories = [
            Category::factory()->for(User::factory())->create(['type' => 'expense']),
            Category::factory()->for($user)->create(['type' => 'income']),
            Category::factory()->for($user)->create(['type' => 'expense', 'status' => 'inactive']),
        ];
        foreach ($categories as $category) {
            $this->actingAs($user)->put(route('financial-settings.update'), $this->payload([
                'essentials' => [['category_id' => $category->id, 'amount' => '1.00']],
            ]))->assertSessionHasErrors(['essentials.0.category_id' => 'Escolha uma categoria de despesa disponível.']);
        }
        $this->assertDatabaseCount('monthly_financial_settings', 0);
        $this->assertDatabaseCount('essential_budgets', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_settings_do_not_write(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create())->put(route('financial-settings.update'), $this->payload($changes))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('monthly_financial_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidValues(): array
    {
        return [
            'negative' => [['protection_value' => '-1'], 'protection_value'],
            'precision' => [['protection_value' => '1.001'], 'protection_value'],
            'overflow' => [['protection_value' => '100000000000000000.00'], 'protection_value'],
            'exponent' => [['protection_value' => '1e2'], 'protection_value'],
            'localized' => [['protection_value' => '20,00'], 'protection_value'],
            'percent' => [['protection_type' => 'percentage', 'protection_value' => '100.01'], 'protection_value'],
            'type' => [['protection_type' => 'both'], 'protection_type'],
            'month' => [['month' => '2026-13'], 'month'],
            'version' => [['version' => -1], 'version'],
            'nested injection' => [['essentials' => [['category_id' => 1, 'amount' => '20', 'user_id' => 1]]], 'essentials.0'],
        ];
    }

    public function test_duplicate_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $row = ['category_id' => $category->id, 'amount' => '10.00'];

        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload(['essentials' => [$row, $row]]))
            ->assertSessionHasErrors('essentials.0.category_id');
        $this->assertDatabaseCount('essential_budgets', 0);
    }

    public function test_stale_version_does_not_overwrite_or_duplicate_audits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload())->assertRedirect();

        $this->put(route('financial-settings.update'), $this->payload(['protection_value' => '9.00']))
            ->assertSessionHasErrors(['version' => 'Esta configuração mudou em outra aba. Recarregue antes de salvar.']);
        $this->assertSame('0.00', MonthlyFinancialSetting::sole()->protection_value);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_inactive_linked_category_can_be_kept_but_not_reintroduced_after_removal(): void
    {
        $user = User::factory()->create();
        $setting = MonthlyFinancialSetting::factory()->for($user)->create();
        $budget = EssentialBudget::factory()->for($setting)->create();
        $budget->category->update(['status' => 'inactive']);
        $data = $this->payload(['version' => 1, 'protection_type' => 'percentage', 'protection_value' => '100.00',
            'essentials' => [['category_id' => $budget->category_id, 'amount' => '0.01']]]);

        $this->actingAs($user)->put(route('financial-settings.update'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.01', $budget->fresh()->amount);
        $this->assertSame('100.00', $setting->fresh()->protection_value);
        $this->put(route('financial-settings.update'), $this->payload(['version' => 2]))->assertRedirect();
        $this->put(route('financial-settings.update'), array_replace($data, ['version' => 3]))->assertSessionHasErrors('essentials.0.category_id');
        $this->assertSoftDeleted($budget);
    }

    public function test_unsupported_value_above_exact_local_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload(['protection_value' => '10000000000.00']))->assertSessionHasErrors('protection_value');
        $this->assertDatabaseCount('monthly_financial_settings', 0);
    }

    public function test_removing_and_readding_budget_preserves_record_and_audit(): void
    {
        $user = User::factory()->create();
        $setting = MonthlyFinancialSetting::factory()->for($user)->create();
        $budget = EssentialBudget::factory()->for($setting)->create();

        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload(['version' => 1]))->assertRedirect();
        $this->assertSoftDeleted($budget);
        $this->put(route('financial-settings.update'), $this->payload(['version' => 2,
            'essentials' => [['category_id' => $budget->category_id, 'amount' => '650.00']],
        ]))->assertRedirect();
        $this->assertSame('650.00', $budget->fresh()->amount);
        $this->assertNull($budget->fresh()->deleted_at);
        $this->assertDatabaseCount('essential_budgets', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restored', 'auditable_type' => 'essential_budget']);
    }

    public function test_audit_failure_rolls_back_settings_and_budgets(): void
    {
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));

        $this->actingAs(User::factory()->create())->put(route('financial-settings.update'), $this->payload())->assertServerError();
        $this->assertDatabaseCount('monthly_financial_settings', 0);
        $this->assertDatabaseCount('essential_budgets', 0);
    }

    public function test_category_shortcut_creates_expense_and_returns_to_selected_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('financial-settings.categories.store'), ['month' => '2026-10', 'name' => '  Farmácia  ', 'type' => 'income'])
            ->assertRedirect(route('financial-settings.edit', ['month' => '2026-10']));
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'type' => 'expense', 'name' => 'Farmácia']);
        $this->assertDatabaseCount('monthly_financial_settings', 0);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['month' => '2026-09', 'version' => 0, 'protection_type' => 'fixed', 'protection_value' => '0.00', 'essentials' => []], $changes);
    }

    public function test_budget_audit_failure_rolls_back_soft_deletion_and_version(): void
    {
        $user = User::factory()->create();
        $setting = MonthlyFinancialSetting::factory()->for($user)->create();
        $budget = EssentialBudget::factory()->for($setting)->create();
        $realRecorder = new AuditRecorder;
        $this->mock(AuditRecorder::class)->shouldReceive('record')->twice()
            ->andReturnUsing(function ($actor, $action, $model, $before = null) use ($realRecorder) {
                if ($model instanceof EssentialBudget) {
                    throw new RuntimeException('budget audit unavailable');
                }

                return $realRecorder->record($actor, $action, $model, $before);
            });

        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload(['version' => 1]))->assertServerError();

        $this->assertNull($budget->fresh()->deleted_at);
        $this->assertSame(1, $setting->fresh()->version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_largest_supported_setting_round_trips_without_losing_cents(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $this->actingAs($user)->put(route('financial-settings.update'), $this->payload([
            'protection_value' => '9999999999.99',
            'essentials' => [['category_id' => $category->id, 'amount' => '9999999999.99']],
        ]))->assertRedirect();

        $this->assertSame('9999999999.99', MonthlyFinancialSetting::sole()->protection_value);
        $this->assertSame('9999999999.99', EssentialBudget::sole()->amount);
    }
}
