<?php

namespace Tests\Feature;

use App\Models\NfcAccessLink;
use App\Models\NfcRecord;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NfcWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login_for_reads_and_writes(): void
    {
        $this->get(route('nfc.index'))->assertRedirect(route('login'));
        $this->post(route('nfc.companies.store'))->assertRedirect(route('login'));
    }

    public function test_plugin_page_remains_available_while_the_migration_is_pending(): void
    {
        $this->actingAs(User::factory()->create());
        Schema::drop('nfc_records');

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Nfc/Index')
            ->where('schema_pending', true));
        $this->post(route('nfc.companies.store'))
            ->assertStatus(303)
            ->assertRedirect(route('nfc.index'));
    }

    public function test_companies_and_mutations_are_scoped_to_the_authenticated_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $company = $this->createCompany($owner);
        $visible = $this->createCompany($other, 'Outra empresa');

        $this->actingAs($other)->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Nfc/Index')
            ->has('companies', 1)
            ->where('companies.0.id', $visible->id)
            ->where('summary.interested', 1));

        $this->patch(route('nfc.companies.update', ['company' => $company->id]), [
            'name' => 'Nome alterado',
            'interested' => false,
        ])->assertNotFound();

        $this->post(route('nfc.units.store', ['company' => $company->id]), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Filial alheia',
        ])->assertNotFound();

        $this->assertSame($company->payload, $company->fresh()->payload);
    }

    public function test_initial_batch_accepts_unknown_cost_and_rejects_a_conflicting_replay(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = [
            'operation_id' => (string) Str::uuid(),
            'initial' => true,
            'quantity' => 60,
            'cost_cents' => null,
        ];

        $this->post(route('nfc.production.store'), $payload)->assertRedirect(route('nfc.index'));
        $this->post(route('nfc.production.store'), $payload)->assertRedirect(route('nfc.index'));
        $this->post(route('nfc.production.store'), array_replace($payload, [
            'quantity' => 61,
        ]))->assertSessionHasErrors('quantity');

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('production', 1)
            ->where('production.0.initial', true)
            ->where('production.0.quantity', 60)
            ->where('production.0.cost_cents', null)
            ->where('summary.manufactured', 60)
            ->where('summary.unknown_cost_batches', 1));
    }

    public function test_mixed_payments_reduce_the_balance_and_quit_the_sale_only_once(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-11T15:00:00Z'));
        $company = $this->createCompany(User::factory()->create());
        $sale = $this->createSale($company, [
            'sold_on' => '2026-11-10',
            'standard_quantity' => 2,
            'custom_quantity' => 2,
            'in_customization_quantity' => 1,
            'payments' => [['method' => 'cash', 'amount_cents' => 4000]],
        ]);

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.units.0.name', 'Unidade 1')
            ->where('companies.0.sales.0.unit_id', $company->payload['units'][0]['id'])
            ->has('companies.0.sales.0.plates', 4)
            ->where('companies.0.sales.0.payment_status', 'partial')
            ->where('summary.sold_standard', 2)
            ->where('summary.sold_custom', 2)
            ->where('summary.in_customization', 1)
            ->where('summary.manufactured', 0)
            ->where('summary.paid_plates', 0)
            ->where('summary.received_cents', 4000)
            ->where('summary.outstanding_cents', 6000));

        $paymentRoute = route('nfc.payments.store', ['company' => $company->id, 'sale' => $sale['id']]);
        $payment = ['operation_id' => (string) Str::uuid(), 'method' => 'pix', 'amount_cents' => 6000];

        $this->post($paymentRoute, array_replace($payment, ['amount_cents' => 6001]))
            ->assertSessionHasErrors('amount_cents');
        $this->post($paymentRoute, $payment)->assertRedirect(route('nfc.index'));
        $this->post($paymentRoute, $payment)->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('companies.0.sales.0.payments', 2)
            ->where('companies.0.sales.0.payments.0.method', 'cash')
            ->where('companies.0.sales.0.payments.1.method', 'pix')
            ->where('companies.0.sales.0.payment_status', 'paid')
            ->where('summary.paid_plates', 4)
            ->where('summary.received_cents', 10000)
            ->where('summary.outstanding_cents', 0));
    }

    public function test_warranty_clamps_to_month_end_and_reminds_for_seven_days_through_expiry(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-04-22T15:00:00Z'));
        $company = $this->createCompany(User::factory()->create());
        $this->createSale($company, ['sold_on' => '2026-01-31']);

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.sales.0.warranty_until', '2026-04-30')
            ->has('warranty_notices', 0));

        $this->travelTo(new DateTimeImmutable('2026-04-23T15:00:00Z'));
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('warranty_notices', 1)
            ->where('warranty_notices.0.company_id', $company->id)
            ->where('warranty_notices.0.warranty_until', '2026-04-30'));

        $this->travelTo(new DateTimeImmutable('2026-05-01T02:59:00Z'));
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.sales.0.warranty_status', 'active')
            ->has('warranty_notices', 1));

        $this->travelTo(new DateTimeImmutable('2026-05-01T03:00:00Z'));
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.sales.0.warranty_status', 'expired')
            ->has('warranty_notices', 0));
    }

    public function test_manual_notices_can_stand_alone_or_link_to_a_plate_and_be_resolved(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-11T15:00:00Z'));
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $sale = $this->createSale($company, ['sold_on' => '2026-11-10']);
        $plate = $sale['plates'][0];
        $free = ['operation_id' => (string) Str::uuid(), 'text' => 'Comprar embalagens', 'important' => false];
        $linked = [
            'operation_id' => (string) Str::uuid(),
            'text' => 'Conferir a plaquinha do balcão',
            'important' => true,
            'company_id' => $company->id,
            'plate_id' => $plate['id'],
        ];

        $this->post(route('nfc.notices.store'), $free)->assertRedirect(route('nfc.index'));
        $this->post(route('nfc.notices.store'), $linked)->assertRedirect(route('nfc.index'));

        $notice = NfcRecord::query()->where('user_id', $owner->id)->where('record_key', $linked['operation_id'])->firstOrFail();
        $this->assertSame(2, NfcRecord::query()->where('kind', NfcRecord::NOTICE)->count());
        $this->assertSame($plate['id'], $notice->payload['plate_id']);

        $resolveRoute = route('nfc.notices.resolve', ['notice' => $notice->id]);
        $this->actingAs(User::factory()->create());
        $this->post(route('nfc.notices.store'), array_replace($linked, [
            'operation_id' => (string) Str::uuid(),
        ]))->assertSessionHasErrors('company_id');
        $this->patch($resolveRoute)->assertNotFound();
        $this->assertNull($notice->fresh()->payload['resolved_at']);

        $this->actingAs($owner)->patch($resolveRoute)->assertRedirect(route('nfc.index'));
        $this->assertNotNull($notice->fresh()->payload['resolved_at']);
        $this->patch($resolveRoute)->assertRedirect(route('nfc.index'));
        $this->assertNull($notice->fresh()->payload['resolved_at']);
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('notices', fn ($notices) => collect($notices)->contains(
                fn (array $row) => $row['text'] === 'Comprar embalagens' && $row['company_id'] === null,
            ))
            ->where('notices', fn ($notices) => collect($notices)->contains(
                fn (array $row) => $row['id'] === $notice->id && $row['company_name'] === 'Pontasso'
                    && $row['unit_name'] === 'Unidade 1' && $row['plate_label'] === 'Normal 1',
            )));
    }

    public function test_production_status_and_unit_link_are_scoped_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->post(route('nfc.production.store'), [
            'operation_id' => (string) Str::uuid(),
            'initial' => false,
            'status' => 'in_progress',
            'quantity' => 10,
        ])->assertRedirect(route('nfc.index'));
        $batch = NfcRecord::whereBelongsTo($owner)->where('kind', NfcRecord::PRODUCTION)->firstOrFail();
        $company = $this->createCompany($owner);
        $unitId = (string) Str::uuid();
        $this->post(route('nfc.units.store', ['company' => $company->id]), [
            'operation_id' => $unitId,
            'name' => 'Filial teste',
            'production_id' => $batch->id,
        ])->assertRedirect(route('nfc.index'));
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.in_production', 10)
            ->where('summary.manufactured', 0)
            ->where('companies.0.units.1.production_id', $batch->id)
            ->where('companies.0.units.1.production_status', 'in_progress'));

        $intruder = User::factory()->create();
        $this->actingAs($intruder);
        $otherCompany = $this->createCompany($intruder, 'Outra');
        $this->post(route('nfc.units.store', ['company' => $otherCompany->id]), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Invasora',
            'production_id' => $batch->id,
        ])->assertSessionHasErrors('production_id');
    }

    public function test_defective_plates_are_counted_and_estimated_losses_do_not_change_revenue(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-11T15:00:00Z'));
        $company = $this->createCompany(User::factory()->create());
        $sale = $this->createSale($company, ['sold_on' => '2026-11-10']);
        $plate = $sale['plates'][0];

        $this->patch(route('nfc.plates.update', [
            'company' => $company->id,
            'sale' => $sale['id'],
            'plate' => $plate['id'],
        ]), [
            'type' => 'standard',
            'label' => 'Defeituosa',
            'notes' => null,
            'customizing' => false,
            'defective' => true,
            'estimated_loss_cents' => 2500,
        ])->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.defective', 1)
            ->where('summary.estimated_loss_cents', 2500)
            ->where('summary.received_cents', 0)
            ->where('summary.outstanding_cents', 10000)
            ->where('companies.0.sales.0.plates.0.defective', true));
    }

    public function test_payment_deletion_restores_outstanding_balance_and_audits(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-11T15:00:00Z'));
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $sale = $this->createSale($company, [
            'sold_on' => '2026-11-10',
            'payments' => [['method' => 'pix', 'amount_cents' => 3000]],
        ]);
        $payment = $company->fresh()->payload['sales'][0]['payments'][0];

        $this->delete(route('nfc.payments.destroy', [
            'company' => $company->id, 'sale' => $sale['id'], 'payment' => $payment['id'],
        ]))->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.received_cents', 0)
            ->where('summary.outstanding_cents', 10000)
            ->has('companies.0.sales.0.payments', 0)
            ->where('history.0.event', 'updated'));
    }

    public function test_deletion_is_audited_and_does_not_expose_removed_data(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->delete(route('nfc.companies.destroy', ['company' => $company->id]))
            ->assertRedirect(route('nfc.index'));

        $this->assertNotNull($company->fresh()->payload['deleted_at']);
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('companies', 0)
            ->where('history.0.event', 'deleted')
            ->where('history.0.target_id', $company->id));

        $this->patch(route('nfc.companies.update', ['company' => $company->id]), [
            'name' => 'Reviver sem auditoria',
            'interested' => true,
        ])->assertNotFound();
    }

    public function test_scoped_shared_link_allows_nfc_only_and_can_be_revoked(): void
    {
        error_log('NFC QA checkpoint: start');
        $other = User::factory()->create();
        $otherCompany = $this->createCompany($other, 'Outra empresa');
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        error_log('NFC QA checkpoint: before-store-link');
        $this->post(route('nfc.share-links.store'))->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        $this->assertNotNull($url);
        preg_match('~/compartilhado/([^/?]+)~', $url, $matches);
        $token = $matches[1];
        $link = NfcAccessLink::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertNotSame($token, $link->token_hash);

        error_log('NFC QA checkpoint: before-guest');
        Auth::logout();
        error_log('NFC QA checkpoint: before-guest-get');
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('Nfc/Index')
            ->where('shared', true)
            ->where('auth.user', null)
            ->has('companies', 1)
            ->where('companies.0.id', $company->id));

        error_log('NFC QA checkpoint: before-auth-guard');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('nfc.companies.store'), [])->assertRedirect(route('login'));
        $this->patch(route('nfc.share.companies.update', [
            'token' => $token, 'company' => $otherCompany->id,
        ]), [
            'name' => 'Invasão', 'interested' => false,
        ])->assertNotFound();

        error_log('NFC QA checkpoint: before-guest-post');
        $this->post(route('nfc.share.companies.store', ['token' => $token]), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Nova pelo link',
            'interested' => true,
        ])->assertRedirect(route('nfc.share.index', ['token' => $token]));
        $this->assertSame(2, NfcRecord::query()->where('user_id', $owner->id)
            ->where('kind', NfcRecord::COMPANY)->count());
        $this->assertSame('Link compartilhado #'.$link->id,
            NfcRecord::query()->where('user_id', $owner->id)->where('kind', NfcRecord::HISTORY)
                ->latest('id')->firstOrFail()->payload['actor']);

        error_log('NFC QA checkpoint: before-revoke');
        $this->actingAs($owner)->delete(route('nfc.share-links.destroy', ['link' => $link->id]))
            ->assertRedirect(route('nfc.index'));
        Auth::logout();
        error_log('NFC QA checkpoint: before-revoked-check');
        $this->get($url)->assertNotFound();
        $this->get(route('nfc.share.index', ['token' => str_repeat('a', 64)]))->assertNotFound();
    }

    public function test_expired_nfc_share_token_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('nfc.share-links.store'))
            ->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        NfcAccessLink::query()->where('user_id', $owner->id)->firstOrFail()
            ->update(['expires_at' => now()->subSecond()]);
        Auth::logout();
        $this->get($url)->assertNotFound();
    }

    private function createCompany(User $user, string $name = 'Pontasso'): NfcRecord
    {
        $operationId = (string) Str::uuid();
        $this->actingAs($user)->post(route('nfc.companies.store'), [
            'operation_id' => $operationId,
            'name' => $name,
            'interested' => true,
        ])->assertRedirect(route('nfc.index'));

        return NfcRecord::query()->where('user_id', $user->id)->where('record_key', $operationId)->firstOrFail();
    }

    private function createSale(NfcRecord $company, array $overrides): array
    {
        $this->post(route('nfc.sales.store', ['company' => $company->id]), array_replace([
            'operation_id' => (string) Str::uuid(),
            'unit_id' => $company->payload['units'][0]['id'],
            'total_cents' => 10000,
            'standard_quantity' => 1,
            'custom_quantity' => 0,
            'in_customization_quantity' => 0,
            'payments' => [],
        ], $overrides))->assertRedirect(route('nfc.index'));

        return $company->fresh()->payload['sales'][0];
    }
}
