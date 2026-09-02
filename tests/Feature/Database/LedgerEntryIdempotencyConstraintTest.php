<?php

namespace Tests\Feature\Database;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerEntryIdempotencyConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_operation_and_type_cannot_target_two_references(): void
    {
        $user = User::factory()->create();
        $firstAccount = Account::factory()->for($user)->create();
        $secondAccount = Account::factory()->for($user)->create();
        $operationId = fake()->uuid();
        LedgerEntry::factory()->for($user)->create([
            'reference_type' => 'account',
            'reference_id' => $firstAccount->id,
            'type' => LedgerEntryType::Income,
            'operation_id' => $operationId,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        LedgerEntry::factory()->for($user)->create([
            'reference_type' => 'account',
            'reference_id' => $secondAccount->id,
            'type' => LedgerEntryType::Income,
            'operation_id' => $operationId,
        ]);
    }
}
