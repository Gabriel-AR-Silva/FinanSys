<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DevelopmentFinancialSeeder;
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
}
