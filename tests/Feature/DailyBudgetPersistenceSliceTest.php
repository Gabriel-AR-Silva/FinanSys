<?php

namespace Tests\Feature;

use App\Actions\SetDailyBudget;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DailyBudgetPersistenceSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_version_and_audit_without_affecting_another_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $key = (string) Str::uuid();
        $action = app(SetDailyBudget::class);

        $one = $action->handle($first, '90', $key, 'Planejamento');
        $two = $action->handle($second, '120', $key);

        $this->assertSame('90.00', $one->amount);
        $this->assertSame('120.00', $two->amount);
        $this->assertSame((int) $first->id, (int) $one->actor_id);
        $this->assertSame('manual', $one->origin);
        $this->assertDatabaseCount('daily_budget_versions', 2);
        $this->assertSame(1, AuditLog::query()->where('user_id', $first->id)->where('action', AuditAction::Created)->count());
        $this->assertSame(1, AuditLog::query()->where('user_id', $second->id)->where('action', AuditAction::Created)->count());
    }

    public function test_idempotent_replay_and_conflict_do_not_create_extra_rows(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $action = app(SetDailyBudget::class);
        $first = $action->handle($user, '90', $key);

        $this->assertSame($first->id, $action->handle($user, '90.00', $key)->id);
        try {
            $action->handle($user, '91.00', $key);
            $this->fail('Conflicting replay must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('daily_budget_versions', 1);
            $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_version_is_append_only_through_eloquent(): void
    {
        $version = app(SetDailyBudget::class)->handle(User::factory()->create(), '30', (string) Str::uuid());
        try {
            $version->update(['amount' => '40.00']);
            $this->fail('Updating a version must fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('daily_budget_versions', ['id' => $version->id, 'amount' => '30.00']);
        }

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_rejects_amount_outside_decimal_storage_without_writes(): void
    {
        $user = User::factory()->create();
        try {
            app(SetDailyBudget::class)->handle($user, '100000000000000000', (string) Str::uuid());
            $this->fail('Amount outside DECIMAL(19,2) must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('daily_budget_versions', 0);
            $this->assertSame(0, AuditLog::query()->where('user_id', $user->id)->count());
        }
    }
}
