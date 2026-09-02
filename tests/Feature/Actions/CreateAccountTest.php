<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateAccount;
use App\Enums\LedgerEntryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class CreateAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_opening_balance_creates_an_audited_entry(): void
    {
        $user = User::factory()->create();

        $account = app(CreateAccount::class)->handle($user, 'Principal', '1000.00');

        $this->assertSame('1000.00', $account->ledgerEntries()->sole()->amount);
        $this->assertSame(LedgerEntryType::OpeningBalance, $account->ledgerEntries()->sole()->type);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_zero_opening_balance_does_not_create_an_entry(): void
    {
        $user = User::factory()->create();

        $account = app(CreateAccount::class)->handle($user, 'Zerada', '0');

        $this->assertDatabaseCount('accounts', 1);
        $this->assertSame(0, $account->ledgerEntries()->count());
    }

    public function test_negative_opening_balance_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CreateAccount::class)->handle(User::factory()->create(), 'Inválida', '-0.01');
    }

    public function test_repeated_operation_id_returns_the_existing_account(): void
    {
        $user = User::factory()->create();
        $operationId = 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31';

        $first = app(CreateAccount::class)->handle($user, 'Principal', '100.00', $operationId);
        $repeated = app(CreateAccount::class)->handle($user, 'Principal', '100.00', $operationId);

        $this->assertSame($first->id, $repeated->id);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_repeated_operation_id_rejects_different_account_parameters(): void
    {
        $user = User::factory()->create();
        $operationId = 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31';
        app(CreateAccount::class)->handle($user, 'Principal', '100.00', $operationId);

        try {
            app(CreateAccount::class)->handle($user, 'Outra conta', '100.00', $operationId);
            $this->fail('A chave idempotente deveria rejeitar parâmetros diferentes.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Esta chave de operação já foi usada com dados diferentes.'],
                $exception->errors()['operation_id'],
            );
        }

        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_repeated_operation_id_rejects_a_different_opening_balance(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();
        $operationId = 'e42062a8-2c8d-4fd1-920d-d1b64cf24e31';
        app(CreateAccount::class)->handle($user, 'Principal', '100.00', $operationId);

        app(CreateAccount::class)->handle($user, 'Principal', '200.00', $operationId);
    }
}
