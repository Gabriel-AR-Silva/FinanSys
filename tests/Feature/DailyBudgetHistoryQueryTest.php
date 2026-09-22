<?php

namespace Tests\Feature;

use App\Models\DailyBudgetVersion;
use App\Models\User;
use App\Queries\DailyBudgetHistoryQuery;
use App\Support\DailyBudgetVersionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyBudgetHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_utc_storage_without_shifting_the_sao_paulo_day_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->store($user, '90.00', '2026-09-22 02:59:59');
        $this->store($user, '120.00', '2026-09-22 03:00:00');
        $this->store($other, '999.00', '2026-09-22 02:00:00');

        $versions = (new DailyBudgetHistoryQuery)->forUser($user);

        $this->assertCount(2, $versions);
        $this->assertSame('2026-09-22T02:59:59+00:00', $versions[0]['effective_at']);
        $this->assertSame('2026-09-22T03:00:00+00:00', $versions[1]['effective_at']);
        $this->assertSame('2026-09-22T03:00:00+00:00', $versions[1]['recorded_at']);

        $selector = new DailyBudgetVersionSelector;
        $this->assertSame(
            ['id' => $versions[0]['id'], 'amount' => '90.00'],
            $selector->resolve($user->id, '2026-09-21', '2026-09-22T10:00:00-03:00', $versions)
        );
        $this->assertSame(
            ['id' => $versions[1]['id'], 'amount' => '120.00'],
            $selector->resolveOpenDay($user->id, '2026-09-22', '2026-09-22T00:00:00-03:00', $versions)
        );
    }

    private function store(User $user, string $amount, string $instant): void
    {
        DailyBudgetVersion::query()->create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'amount' => $amount,
            'effective_at' => $instant,
            'recorded_at' => $instant,
            'origin' => 'manual',
            'operation_id' => (string) Str::uuid(),
        ]);
    }
}
