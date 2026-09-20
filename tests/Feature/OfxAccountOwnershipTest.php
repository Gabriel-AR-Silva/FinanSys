<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxAccountOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_import_statement_into_another_users_account(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = Account::factory()->for($owner)->create();

        $this->actingAs($otherUser)->post(route('ofx-imports.store'), [
            'account_id' => $foreignAccount->getKey(),
            'file' => UploadedFile::fake()->createWithContent('statement.ofx', 'invalid content'),
        ])->assertNotFound();

        $this->assertDatabaseCount('bank_statement_imports', 0);
        $this->assertDatabaseCount('bank_statement_import_items', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
