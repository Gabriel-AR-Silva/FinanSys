<?php

namespace Tests\Feature;

use App\Actions\RecordForecastReceipt;
use App\Models\Account;
use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardReceivablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivables_only_include_current_month_pending_amount_for_authenticated_user(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-18T15:00:00Z'));
        $user = User::factory()->create();
        ReceiptForecast::factory()->for($user)->create(['amount' => '100.00', 'expected_on' => '2026-09-22']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '25.50', 'expected_on' => '2026-09-20']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '900.00', 'expected_on' => '2026-09-19', 'status' => 'cancelled']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '800.00', 'expected_on' => '2026-10-01']);
        ReceiptForecast::factory()->create(['amount' => '700.00', 'expected_on' => '2026-09-18']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('receivables.month', '2026-09')
                ->where('receivables.pending', '125.50')
                ->where('receivables.next_due_on', '2026-09-20')
                ->etc());
    }

    public function test_partial_receipt_reduces_receivables_without_counting_forecast_as_income(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-18T15:00:00Z'));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $forecast = ReceiptForecast::factory()->for($user)->for($category)->create([
            'amount' => '100.00', 'expected_on' => '2026-09-20',
        ]);

        $before = $this->actingAs($user)->get(route('dashboard'));
        $before->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->where('receivables.pending', '100.00')->where('overview.period_summary.income', '0')->etc());

        app(RecordForecastReceipt::class)->handle($user, $forecast->id, [
            'account_id' => $account->id, 'amount' => '40.00', 'occurred_at' => '2026-09-18',
            'forecast_version' => $forecast->fresh()->version, 'operation_id' => (string) Str::uuid(),
        ]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->where('receivables.pending', '60.00')->where('receivables.next_due_on', '2026-09-20')
            ->where('overview.period_summary.income', '40.00')->etc());
    }
}
