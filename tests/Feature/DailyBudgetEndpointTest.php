<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyBudgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_budget(): void
    {
        $this->post(route('daily-budgets.store'), [
            'amount' => '90.00',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('daily_budget_versions', 0);
    }

    public function test_authenticated_user_can_save_and_replay_a_budget_without_duplicate_audit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $operationId = (string) Str::uuid();

        $this->actingAs($user)->post(route('daily-budgets.store'), [
            'amount' => '90',
            'operation_id' => $operationId,
            'reason' => 'Planejamento',
            'user_id' => $other->id,
        ])->assertRedirect(route('financial-settings.edit'));

        $this->assertDatabaseHas('daily_budget_versions', [
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'amount' => '90.00',
            'operation_id' => $operationId,
        ]);
        $this->assertDatabaseMissing('daily_budget_versions', ['user_id' => $other->id]);

        $this->post(route('daily-budgets.store'), [
            'amount' => '90.00',
            'operation_id' => $operationId,
            'reason' => 'Planejamento',
        ])->assertRedirect(route('financial-settings.edit'));

        $this->assertDatabaseCount('daily_budget_versions', 1);
        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->where('action', AuditAction::Created)->count());
    }

    public function test_conflicting_replay_and_invalid_values_leave_no_partial_writes(): void
    {
        $user = User::factory()->create();
        $operationId = (string) Str::uuid();
        $this->actingAs($user)->post(route('daily-budgets.store'), [
            'amount' => '90',
            'operation_id' => $operationId,
        ])->assertRedirect(route('financial-settings.edit'));

        $this->post(route('daily-budgets.store'), [
            'amount' => '120',
            'operation_id' => $operationId,
        ])->assertSessionHasErrors('operation_id');

        foreach (['-1', '1.001', '100000000000000000'] as $amount) {
            $this->post(route('daily-budgets.store'), [
                'amount' => $amount,
                'operation_id' => (string) Str::uuid(),
            ])->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseCount('daily_budget_versions', 1);
        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
    }
}
