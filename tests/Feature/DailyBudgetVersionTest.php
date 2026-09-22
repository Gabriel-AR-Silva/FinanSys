<?php

namespace Tests\Feature;

use App\Actions\SetDailyBudget;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\DailyBudgetVersionSelector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DailyBudgetVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_immediate_budget_with_audited_version_and_resolves_it_for_today(): void
    {
        $user = User::factory()->create();
        $operationId = (string) Str::uuid();
        $version = app(SetDailyBudget::class)->handle($user, '90', $operationId, 'Planejamento pessoal');

        $this->assertSame('90.00', $version->amount);
        $this->assertSame('manual', $version->origin);
        $this->assertSame((int) $user->id, (int) $version->actor_id);
        $this->assertDatabaseCount('daily_budget_versions', 1);
        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->where('action', AuditAction::Created)->count());

        $effectiveAt = CarbonImmutable::instance($version->effective_at);
        $localDay = $effectiveAt->setTimezone('America/Sao_Paulo')->toDateString();
        $observedAt = $effectiveAt->addSecond()->format('Y-m-d\TH:i:sP');
        $selected = (new DailyBudgetVersionSelector)->resolveOpenDay($user->id, $localDay, $observedAt, [
            [
                'id' => $version->id,
                'user_id' => $user->id,
                'amount' => $version->amount,
                'effective_at' => $version->effective_at->format('Y-m-d\TH:i:sP'),
                'recorded_at' => $version->recorded_at->format('Y-m-d\TH:i:sP'),
            ],
        ]);

        $this->assertSame(['id' => $version->id, 'amount' => '90.00'], $selected);
    }

    public function test_repeating_the_same_operation_returns_the_same_version_without_a_second_audit(): void
    {
        $user = User::factory()->create();
        $operationId = (string) Str::uuid();
        $action = app(SetDailyBudget::class);
        $original = $action->handle($user, '90', $operationId, 'Planejamento pessoal');
        $replayed = $action->handle($user, '90.00', $operationId, 'Planejamento pessoal');

        $this->assertSame($original->id, $replayed->id);
        $this->assertDatabaseCount('daily_budget_versions', 1);
        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
    }

    public function test_same_key_with_changed_amount_is_rejected_without_partial_writes(): void
    {
        $user = User::factory()->create();
        $operationId = (string) Str::uuid();
        $action = app(SetDailyBudget::class);
        $action->handle($user, '90', $operationId);

        try {
            $action->handle($user, '120', $operationId);
            $this->fail('A conflicting replay must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('daily_budget_versions', 1);
            $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_operation_keys_are_scoped_to_the_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $operationId = (string) Str::uuid();
        $action = app(SetDailyBudget::class);

        $firstVersion = $action->handle($first, '90', $operationId);
        $secondVersion = $action->handle($second, '120', $operationId);

        $this->assertNotSame($firstVersion->id, $secondVersion->id);
        $this->assertSame((int) $first->id, (int) $firstVersion->user_id);
        $this->assertSame((int) $second->id, (int) $secondVersion->user_id);
        $this->assertDatabaseCount('daily_budget_versions', 2);
    }

    public function test_invalid_amount_creates_neither_version_nor_audit(): void
    {
        $user = User::factory()->create();

        try {
            app(SetDailyBudget::class)->handle($user, '100000000000000000', (string) Str::uuid());
            $this->fail('Amount outside DECIMAL(19,2) must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('daily_budget_versions', 0);
            $this->assertSame(0, AuditLog::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_a_saved_version_cannot_be_overwritten_or_deleted_through_eloquent(): void
    {
        $user = User::factory()->create();
        $version = app(SetDailyBudget::class)->handle($user, '90', (string) Str::uuid());

        try {
            $version->update(['amount' => '120.00']);
            $this->fail('An existing version must be append-only.');
        } catch (LogicException) {
            $this->assertDatabaseHas('daily_budget_versions', ['id' => $version->id, 'amount' => '90.00']);
        }

        $this->expectException(LogicException::class);
        $version->delete();
    }
}
