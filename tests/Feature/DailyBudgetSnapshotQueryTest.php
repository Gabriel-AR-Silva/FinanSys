<?php

namespace Tests\Feature;

use App\Models\DailyBudgetVersion;
use App\Models\User;
use App\Queries\DailyBudgetSnapshotQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyBudgetSnapshotQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_day_and_check_in_use_persisted_versions_at_the_requested_instant(): void
    {
        $user = User::factory()->create();
        $initial = $this->store($user, '90.00', '2026-09-21 12:00:00');
        $changed = $this->store($user, '120.00', '2026-09-21 18:00:00');
        $later = $this->store($user, '150.00', '2026-09-22 02:00:00');
        $this->store(User::factory()->create(), '999.00', '2026-09-21 17:00:00');

        $query = app(DailyBudgetSnapshotQuery::class);
        $this->assertSame(['id' => $initial->id, 'amount' => '90.00'], $query->forOpenDay($user, '2026-09-21', '2026-09-21T14:59:59-03:00'));
        $this->assertSame(['id' => $changed->id, 'amount' => '120.00'], $query->forOpenDay($user, '2026-09-21', '2026-09-21T15:00:00-03:00'));
        $this->assertSame(['id' => $changed->id, 'amount' => '120.00'], $query->forCheckIn($user, '2026-09-21', '2026-09-21T22:00:00-03:00'));
        $this->assertSame(['id' => $later->id, 'amount' => '150.00'], $query->forCheckIn($user, '2026-09-21', '2026-09-22T09:00:00-03:00'));
        $this->assertNull($query->forCheckIn($user, '2026-09-20', '2026-09-21T09:00:00-03:00'));
    }

    public function test_user_without_budget_does_not_inherit_another_users_history(): void
    {
        $user = User::factory()->create();
        $this->store(User::factory()->create(), '90.00', '2026-09-21 12:00:00');
        $query = app(DailyBudgetSnapshotQuery::class);

        $this->assertNull($query->forOpenDay($user, '2026-09-21', '2026-09-21T15:00:00-03:00'));
        $this->assertNull($query->forCheckIn($user, '2026-09-21', '2026-09-22T08:00:00-03:00'));
    }

    private function store(User $user, string $amount, string $utcInstant): DailyBudgetVersion
    {
        return DailyBudgetVersion::query()->create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'amount' => $amount,
            'effective_at' => $utcInstant,
            'recorded_at' => $utcInstant,
            'origin' => 'manual',
            'operation_id' => (string) Str::uuid(),
        ]);
    }
}
