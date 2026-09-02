<?php

namespace Tests\Feature;

use App\Actions\CreatePocket;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatePocketTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_zero_balance_pocket_idempotently_without_entries(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $key = '3203c8f8-2d58-4ee7-bc25-e48425aaf889';

        $first = app(CreatePocket::class)->handle($user, $account->id, 'Reserva', $key);
        $second = app(CreatePocket::class)->handle($user, $account->id, 'Reserva', $key);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('pockets', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_rejects_reused_operation_key_with_different_payload(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $key = fake()->uuid();
        app(CreatePocket::class)->handle($user, $account->id, 'Reserva', $key);

        $this->expectException(ValidationException::class);

        app(CreatePocket::class)->handle($user, $account->id, 'Viagem', $key);
    }

    public function test_rejects_another_users_account(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();

        $this->expectException(ModelNotFoundException::class);

        app(CreatePocket::class)->handle($attacker, $account->id, 'Invasão');
    }

    public function test_rejects_invalid_operation_id_before_any_write(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        try {
            app(CreatePocket::class)->handle($user, $account->id, 'Reserva', 'invalid');
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('Informe uma chave de operação UUID válida.', $exception->errors()['operation_id'][0]);
        }

        $this->assertDatabaseCount('pockets', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
