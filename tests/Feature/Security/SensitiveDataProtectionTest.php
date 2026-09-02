<?php

namespace Tests\Feature\Security;

use App\Actions\CreateAccount;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class SensitiveDataProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_and_reset_tokens_are_stored_as_non_reversible_hashes(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'segredo-forte']);

        $this->post('/forgot-password', ['email' => $user->email]);

        $this->assertTrue(Hash::check('segredo-forte', $user->fresh()->password));
        $this->assertNotSame('segredo-forte', $user->fresh()->password);
        Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) use ($user): bool {
            $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

            $this->assertNotSame($notification->token, $storedToken);
            $this->assertTrue(Hash::check($notification->token, $storedToken));

            return true;
        });
    }

    public function test_audit_snapshots_are_encrypted_at_rest_and_readable_through_the_model(): void
    {
        $user = User::factory()->create();

        app(CreateAccount::class)->handle($user, 'Conta confidencial', '125.00');

        $audit = AuditLog::query()->where('auditable_type', 'account')->sole();
        $rawAfter = DB::table('audit_logs')->where('id', $audit->id)->value('after');
        $this->assertStringNotContainsString('Conta confidencial', $rawAfter);
        $this->assertSame('Conta confidencial', $audit->after['name']);
    }

    public function test_audit_records_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $user = User::factory()->create();
        app(CreateAccount::class)->handle($user, 'Principal', '0');
        $audit = AuditLog::query()->sole();

        try {
            $audit->update(['action' => 'tampered']);
            $this->fail('A atualização do registro de auditoria deveria ser recusada.');
        } catch (LogicException) {
            $this->assertSame('created', $audit->fresh()->action);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }
}
