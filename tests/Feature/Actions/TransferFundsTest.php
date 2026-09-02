<?php

namespace Tests\Feature\Actions;

use App\Actions\TransferFunds;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferFundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_creates_two_audited_entries_with_one_operation_id(): void
    {
        $user = User::factory()->create();
        $source = Account::query()->create(['user_id' => $user->id, 'name' => 'Origem']);
        $destination = Account::query()->create(['user_id' => $user->id, 'name' => 'Destino']);
        $this->credit($user, $source, '100.00');

        $entries = app(TransferFunds::class)->handle($user, $source, $destination, '50.00', 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31');

        $this->assertSame(LedgerEntryType::TransferOut, $entries['out']->type);
        $this->assertSame(LedgerEntryType::TransferIn, $entries['in']->type);
        $this->assertSame($entries['out']->operation_id, $entries['in']->operation_id);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_transfer_rejects_a_destination_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $source = Account::query()->create(['user_id' => $user->id, 'name' => 'Origem']);
        $destination = Account::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'Alheia']);
        $this->credit($user, $source, '100.00');
        $this->expectException(ValidationException::class);

        app(TransferFunds::class)->handle($user, $source, $destination, '10.00');
    }

    public function test_repeated_operation_id_returns_the_existing_transfer(): void
    {
        $user = User::factory()->create();
        $source = Account::query()->create(['user_id' => $user->id, 'name' => 'Origem']);
        $destination = Account::query()->create(['user_id' => $user->id, 'name' => 'Destino']);
        $this->credit($user, $source, '100.00');
        $operationId = 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31';

        $first = app(TransferFunds::class)->handle($user, $source, $destination, '50.00', $operationId);
        $repeated = app(TransferFunds::class)->handle($user, $source, $destination, '50.00', $operationId);

        $this->assertSame($first['out']->id, $repeated['out']->id);
        $this->assertSame($first['in']->id, $repeated['in']->id);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_repeated_operation_id_rejects_different_transfer_parameters(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        $this->credit($user, $source, '100.00');
        $operationId = 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31';
        app(TransferFunds::class)->handle($user, $source, $destination, '50.00', $operationId);

        try {
            app(TransferFunds::class)->handle($user, $source, $destination, '60.00', $operationId);
            $this->fail('A chave idempotente deveria rejeitar parâmetros diferentes.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Esta chave de operação já foi usada com dados diferentes.'],
                $exception->errors()['operation_id'],
            );
        }

        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_all_account_and_pocket_combinations_transfer_the_exact_available_balance(): void
    {
        foreach ([['account', 'account'], ['account', 'pocket'], ['pocket', 'account'], ['pocket', 'pocket']] as [$sourceType, $destinationType]) {
            $user = User::factory()->create();
            $source = $this->reference($user, $sourceType, 'Origem');
            $destination = $this->reference($user, $destinationType, 'Destino');
            $this->credit($user, $source, '25.50');

            app(TransferFunds::class)->handle($user, $source, $destination, '25.50', fake()->uuid());

            $this->assertDatabaseHas('ledger_entries', ['reference_type' => $source->getMorphClass(), 'reference_id' => $source->id, 'type' => 'transfer_out', 'amount' => 25.50]);
            $this->assertDatabaseHas('ledger_entries', ['reference_type' => $destination->getMorphClass(), 'reference_id' => $destination->id, 'type' => 'transfer_in', 'amount' => 25.50]);
        }
    }

    public function test_insufficient_balance_rejects_transfer_without_partial_writes_or_audit(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Pocket::factory()->for($user)->for($source)->create();
        $this->credit($user, $source, '9.99');

        try {
            app(TransferFunds::class)->handle($user, $source, $destination, '10.00', fake()->uuid());
            $this->fail('A transferência deveria ser recusada por saldo insuficiente.');
        } catch (ValidationException $exception) {
            $this->assertSame(['O saldo da origem é insuficiente para esta transferência.'], $exception->errors()['amount']);
        }

        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_same_reference_is_rejected_without_writes(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $this->credit($user, $account, '20.00');

        try {
            app(TransferFunds::class)->handle($user, $account, $account, '10.00', fake()->uuid());
            $this->fail('A transferência para a mesma referência deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination', $exception->errors());
        }

        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_invalid_values_are_rejected_without_writes(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        $this->credit($user, $source, '20.00');

        foreach ([['0', fake()->uuid(), 'amount'], ['0.001', fake()->uuid(), 'amount'], ['10.00', 'invalid', 'operation_id']] as [$amount, $operationId, $errorKey]) {
            try {
                app(TransferFunds::class)->handle($user, $source, $destination, $amount, $operationId);
                $this->fail('A transferência inválida deveria ser recusada.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($errorKey, $exception->errors());
            }
        }

        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_inactive_or_deleted_references_are_rejected_inside_the_transaction(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        $this->credit($user, $source, '20.00');
        $destination->delete();

        try {
            app(TransferFunds::class)->handle($user, $source, $destination, '10.00', fake()->uuid());
            $this->fail('A referência excluída deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination_id', $exception->errors());
        }

        $this->assertDatabaseCount('ledger_entries', 1);
    }

    private function reference(User $user, string $type, string $name): Account|Pocket
    {
        if ($type === 'account') {
            return Account::factory()->for($user)->create(['name' => $name]);
        }

        return Pocket::factory()->for($user)->for(Account::factory()->for($user))->create(['name' => $name]);
    }

    private function credit(User $user, Account|Pocket $reference, string $amount): LedgerEntry
    {
        return LedgerEntry::factory()->for($user)->for($reference, 'reference')->income()->create(['amount' => $amount]);
    }
}
