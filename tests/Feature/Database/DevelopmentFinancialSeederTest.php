<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DevelopmentFinancialSeeder;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DevelopmentFinancialSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_idempotent_year_long_demo_scenario_with_factories(): void
    {
        Config::set('development.user.email', 'demo@finansys.test');
        $user = User::factory()->create(['email' => 'demo@finansys.test']);

        $this->seed(DevelopmentFinancialSeeder::class);
        $firstCounts = [$user->accounts()->count(), $user->pockets()->count(), $user->ledgerEntries()->count()];
        $this->seed(DevelopmentFinancialSeeder::class);

        $this->assertSame($firstCounts, [$user->accounts()->count(), $user->pockets()->count(), $user->ledgerEntries()->count()]);
        $this->assertSame(2, $user->accounts()->count());
        $this->assertSame(2, $user->pockets()->count());
        $this->assertGreaterThan(100, $user->ledgerEntries()->count());
        $this->assertTrue($user->ledgerEntries()->oldest('occurred_at')->value('occurred_at')->lt(now()->subYear()));
    }

    public function test_development_scenario_assigns_demo_movements_to_categories(): void
    {
        Config::set('development.user.email', 'demo@finansys.test');
        Config::set('development.user.password', 'local-only-password');
        Config::set('development.seed_demo_data', true);

        $this->seed(DevelopmentSeeder::class);

        $user = User::query()->where('email', 'demo@finansys.test')->firstOrFail();
        $this->assertGreaterThan(0, $user->ledgerEntries()->whereNotNull('category_id')->count());
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $user->id,
            'description' => 'Salário',
            'category_id' => $user->categories()->where('name', 'Salário')->value('id'),
        ]);
    }
}
