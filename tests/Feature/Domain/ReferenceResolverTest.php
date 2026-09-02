<?php

namespace Tests\Feature\Domain;

use App\Domain\Ledger\ReferenceResolver;
use App\Enums\LedgerEntryReferenceType;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferenceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_only_an_account_owned_by_the_user(): void
    {
        $owner = User::factory()->create();
        $account = Account::query()->create(['user_id' => $owner->id, 'name' => 'Conta']);
        $resolved = app(ReferenceResolver::class)->resolve($owner, LedgerEntryReferenceType::Account, $account->id);
        $this->assertTrue($account->is($resolved));
    }

    public function test_it_rejects_a_reference_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $account = Account::query()->create(['user_id' => $owner->id, 'name' => 'Privada']);
        $this->expectException(ValidationException::class);
        app(ReferenceResolver::class)->resolve($intruder, LedgerEntryReferenceType::Account, $account->id);
    }

    public function test_database_rejects_a_pocket_whose_parent_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::query()->create(['user_id' => $other->id, 'name' => 'Outra']);
        $this->expectException(QueryException::class);

        Pocket::query()->create(['user_id' => $owner->id, 'account_id' => $account->id, 'name' => 'Inconsistente']);
    }
}
