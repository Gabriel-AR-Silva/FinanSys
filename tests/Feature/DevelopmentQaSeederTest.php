<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\DailyFinancialCheckIn;
use App\Models\ExpenseCommitment;
use App\Models\FinancialGoal;
use App\Models\PatrimonialAsset;
use App\Models\ReceiptForecast;
use Database\Seeders\DevelopmentQaSeeder;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentQaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_seeder_builds_a_rich_idempotent_local_scenario(): void
    {
        config()->set('development.user.email', 'qa@finansys.local');
        config()->set('development.user.password', 'secret-password');
        config()->set('development.user.name', 'QA FinanSys');
        config()->set('development.seed_demo_data', false);

        $this->seed(DevelopmentSeeder::class);
        $this->seed(DevelopmentQaSeeder::class);

        self::assertSame(1, CardPurchase::query()->where('description', 'Notebook parcelado QA')->count());
        self::assertSame(1, ReceiptForecast::query()->where('amount', '1250.00')->count());
        self::assertSame(1, ExpenseCommitment::query()->where('description', 'Compromisso futuro QA')->count());
        self::assertSame(1, FinancialGoal::query()->where('name', 'Meta de viagem QA')->count());
        self::assertSame(1, PatrimonialAsset::query()->where('name', 'Moto QA')->count());
        self::assertSame(10, DailyFinancialCheckIn::query()->where('reason', 'Cenário QA')->count());

        $this->seed(DevelopmentQaSeeder::class);

        self::assertSame(1, CardPurchase::query()->where('description', 'Notebook parcelado QA')->count());
        self::assertSame(1, FinancialGoal::query()->where('name', 'Meta de viagem QA')->count());
        self::assertSame(10, DailyFinancialCheckIn::query()->where('reason', 'Cenário QA')->count());
    }
}
