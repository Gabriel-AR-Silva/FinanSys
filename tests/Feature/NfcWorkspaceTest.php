<?php

namespace Tests\Feature;

use App\Models\NfcAccessLink;
use App\Models\NfcRecord;
use App\Models\User;
use App\Queries\NfcDashboardQuery;
use App\Queries\NfcReportQuery;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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


    public function test_operating_expense_reduces_available_cash_without_changing_production_cost(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->actingAs($owner);
        $this->post(route('nfc.sales.store', ['company' => $company->id]), [
            'operation_id' => (string) Str::uuid(),
            'unit_id' => $company->payload['units'][0]['id'],
            'sold_on' => '2026-11-21',
            'warranty_starts_on' => '2026-11-21',
            'total_cents' => 10000,
            'standard_quantity' => 1,
            'custom_quantity' => 0,
            'in_customization_quantity' => 0,
            'payments' => [['method' => 'pix', 'amount_cents' => 10000, 'received_on' => '2026-11-21']],
        ])->assertRedirect(route('nfc.index'));

        $this->post(route('nfc.expenses.store'), [
            'operation_id' => (string) Str::uuid(),
            'spent_on' => '2026-11-22',
            'amount_cents' => 1250,
            'category' => 'materials',
            'description' => 'Fita dupla face',
        ])->assertRedirect(route('nfc.index'));

        $dashboard = app(NfcDashboardQuery::class)->forUser($owner);
        $this->assertSame(1250, $dashboard['summary']['expenses_cents']);
        $this->assertSame(8750, $dashboard['summary']['cash_available_cents']);
        $this->assertSame(0, $dashboard['summary']['production_cost_cents']);

        $this->post(route('nfc.expenses.store'), [
            'operation_id' => (string) Str::uuid(),
            'spent_on' => '2026-11-22',
            'amount_cents' => 9000,
            'category' => 'tools',
            'description' => 'Tesoura',
        ])->assertSessionHasErrors('amount_cents');

        $report = app(NfcReportQuery::class)->calculate($dashboard, '2026-11-01', '2026-11-30');
        $this->assertSame(1250, $report['cash']['expenses_cents']);
        $this->assertSame(8750, $report['cash']['net_cents']);
    }

    public function test_manufacturing_damage_updates_summary_and_can_be_removed(): void
    {
        $owner = User::factory()->create();
        $batch = NfcRecord::query()->create([
            'user_id' => $owner->id,
            'kind' => NfcRecord::PRODUCTION,
            'record_key' => (string) Str::uuid(),
            'payload' => ['quantity' => 5, 'cost_cents' => 5000, 'status' => 'completed', 'produced_on' => '2026-11-20'],
        ]);
        $this->actingAs($owner)->post(route('nfc.damages.store'), [
            'operation_id' => (string) Str::uuid(),
            'target' => 'production',
            'production_id' => $batch->id,
            'quantity' => 2,
            'occurred_on' => '2026-11-21',
            'reason' => 'NFC danificado',
            'estimated_loss_cents' => 2000,
        ])->assertRedirect(route('nfc.index'));

        $damage = NfcRecord::query()->whereBelongsTo($owner)->where('kind', NfcRecord::DAMAGE)->firstOrFail();
        $dashboard = app(NfcDashboardQuery::class)->forUser($owner);
        $this->assertSame(2, $dashboard['summary']['defective']);
        $this->assertSame(2000, $dashboard['summary']['estimated_loss_cents']);
        $this->assertSame(5000, $dashboard['summary']['production_cost_cents']);
        $this->assertSame(0, $dashboard['summary']['reinvested_cents']);
        $report = app(NfcReportQuery::class)->calculate($dashboard, '2026-11-01', '2026-11-30');
        $this->assertSame(2, $report['values']['defective']);
        $this->assertSame(2000, $report['values']['loss']);

        $this->post(route('nfc.damages.store'), [
            'operation_id' => (string) Str::uuid(),
            'target' => 'production',
            'production_id' => $batch->id,
            'quantity' => 4,
            'occurred_on' => '2026-11-21',
            'reason' => 'Mais avarias',
        ])->assertSessionHasErrors('quantity');

        $this->delete(route('nfc.damages.destroy', ['damage' => $damage->id]))->assertRedirect(route('nfc.index'));
        $this->assertSame(0, app(NfcDashboardQuery::class)->forUser($owner)['summary']['defective']);
    }

    public function test_damage_cannot_be_attached_to_another_users_batch(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $batch = NfcRecord::query()->create([
            'user_id' => $other->id,
            'kind' => NfcRecord::PRODUCTION,
            'record_key' => (string) Str::uuid(),
            'payload' => ['quantity' => 3, 'status' => 'completed'],
        ]);
        $this->actingAs($owner)->post(route('nfc.damages.store'), [
            'operation_id' => (string) Str::uuid(),
            'target' => 'production',
            'production_id' => $batch->id,
            'quantity' => 1,
            'occurred_on' => '2026-11-21',
            'reason' => 'Problema',
        ])->assertSessionHasErrors('production_id');
        $this->assertDatabaseMissing('nfc_records', ['user_id' => $owner->id, 'kind' => NfcRecord::DAMAGE]);
    }

    public function test_reset_requires_owner_session_and_confirmation_and_only_clears_owners_nfc_records(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();

        foreach ([$owner, $other] as $user) {
            foreach ([NfcRecord::COMPANY, NfcRecord::MANUFACTURER, NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION, NfcRecord::NOTICE, NfcRecord::HISTORY] as $kind) {
                NfcRecord::query()->create([
                    'user_id' => $user->id,
                    'kind' => $kind,
                    'record_key' => (string) Str::uuid(),
                    'payload' => ['name' => 'Teste'],
                ]);
            }
            Storage::disk('local')->put("nfc/manufacturers/{$user->id}/1/test.png", 'test');
        }

        $this->actingAs($owner)->post(route('nfc.share-links.store'), ['scope' => 'cud'])
            ->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        preg_match('~/compartilhado/([^/?]+)~', $url, $matches);
        Auth::logout();

        $this->delete(route('nfc.reset'), ['confirmation' => 'ZERAR'])->assertRedirect(route('login'));
        $this->delete('/plaquinhas-nfc/compartilhado/'.$matches[1].'/zerar', ['confirmation' => 'ZERAR'])->assertNotFound();

        $this->actingAs($owner)->delete(route('nfc.reset'), ['confirmation' => 'ERRADO'])
            ->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('nfc_records', ['user_id' => $owner->id, 'kind' => NfcRecord::COMPANY]);

        $this->delete(route('nfc.reset'), ['confirmation' => 'ZERAR'])->assertRedirect(route('nfc.index'));
        $this->assertDatabaseMissing('nfc_records', ['user_id' => $owner->id]);
        $this->assertSame(6, NfcRecord::query()->whereBelongsTo($other)->count());
        $this->assertDatabaseHas('nfc_access_links', ['user_id' => $owner->id]);
        Storage::disk('local')->assertMissing("nfc/manufacturers/{$owner->id}/1/test.png");
        Storage::disk('local')->assertExists("nfc/manufacturers/{$other->id}/1/test.png");
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

    public function test_new_company_with_blank_first_unit_uses_a_readable_default_name(): void
    {
        $owner = User::factory()->create();
        $operationId = (string) Str::uuid();

        $this->actingAs($owner)->post(route('nfc.companies.store'), [
            'operation_id' => $operationId,
            'name' => 'Empresa teste',
            'interested' => true,
            'unit_name' => '   ',
        ])->assertRedirect(route('nfc.index'));

        $company = NfcRecord::query()->where('user_id', $owner->id)
            ->where('record_key', $operationId)->firstOrFail();

        $this->assertSame('Unidade 1', $company->payload['units'][0]['name']);
    }

    public function test_company_remains_accessible_in_dashboard_after_removing_its_only_unit(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $unitId = $company->payload['units'][0]['id'];

        $this->delete(route('nfc.units.destroy', ['company' => $company->id, 'unit' => $unitId]))
            ->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('companies', 1)
            ->where('companies.0.id', $company->id)
            ->where('companies.0.units', []));
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
        $payment = ['operation_id' => (string) Str::uuid(), 'method' => 'pix', 'amount_cents' => 6000, 'received_on' => '2026-11-11'];

        $this->post($paymentRoute, array_replace($payment, ['amount_cents' => 6001]))
            ->assertSessionHasErrors('amount_cents');
        $this->post($paymentRoute, $payment)->assertRedirect(route('nfc.index'));
        $this->post($paymentRoute, $payment)->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('companies.0.sales.0.payments', 2)
            ->where('companies.0.sales.0.payments.0.method', 'cash')
            ->where('companies.0.sales.0.payments.1.method', 'pix')
            ->where('companies.0.sales.0.payments.1.received_on', '2026-11-11')
            ->where('companies.0.sales.0.payment_status', 'paid')
            ->where('summary.paid_plates', 4)
            ->where('summary.received_cents', 10000)
            ->where('summary.outstanding_cents', 0));
    }

    public function test_reinvestment_tracks_received_cash_without_double_counting_production_cost(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-22T15:00:00Z'));
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->createSale($company, [
            'sold_on' => '2026-11-10',
            'payments' => [['method' => 'pix', 'amount_cents' => 10000, 'received_on' => '2026-11-12']],
        ]);
        $this->post(route('nfc.production.store'), [
            'operation_id' => (string) Str::uuid(),
            'initial' => false,
            'quantity' => 10,
            'produced_on' => '2026-11-19',
            'cost_cents' => 4000,
        ])->assertRedirect(route('nfc.index'));
        $batch = NfcRecord::query()->whereBelongsTo($owner)
            ->where('kind', NfcRecord::PRODUCTION)->firstOrFail();

        $operationId = (string) Str::uuid();
        $payload = [
            'operation_id' => $operationId,
            'spent_on' => '2026-11-20',
            'amount_cents' => 4000,
            'description' => 'Repor plaquinhas personalizadas',
            'production_id' => $batch->id,
        ];

        $this->post(route('nfc.reinvestments.store'), $payload)->assertRedirect(route('nfc.index'));
        $this->post(route('nfc.reinvestments.store'), $payload)->assertRedirect(route('nfc.index'));
        $this->post(route('nfc.reinvestments.store'), array_replace($payload, [
            'amount_cents' => 5000,
        ]))->assertSessionHasErrors('operation_id');
        $this->post(route('nfc.reinvestments.store'), array_replace($payload, [
            'operation_id' => (string) Str::uuid(),
            'amount_cents' => 7000,
        ]))->assertSessionHasErrors('amount_cents');

        $reinvestment = NfcRecord::query()->whereBelongsTo($owner)
            ->where('kind', NfcRecord::REINVESTMENT)->firstOrFail();
        $this->assertSame(1, NfcRecord::query()->whereBelongsTo($owner)->where('kind', NfcRecord::REINVESTMENT)->count());

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.received_cents', 10000)
            ->where('summary.reinvested_cents', 4000)
            ->where('summary.cash_available_cents', 6000)
            ->has('reinvestments', 1)
            ->where('reinvestments.0.description', 'Repor plaquinhas personalizadas'));

        $report = app(NfcReportQuery::class)->calculate(
            app(NfcDashboardQuery::class)->forUser($owner, false),
            '2026-11-01',
            '2026-11-22',
        );
        $this->assertSame(['received_cents' => 10000, 'reinvested_cents' => 4000, 'net_cents' => 6000], $report['cash']);
        $this->assertSame(10000, $report['values']['sales']);
        $this->assertSame(4000, $report['values']['cost']);
        $this->assertSame(6000, $report['values']['result']);

        $pdf = $this->get(route('nfc.report.export', [
            'from' => '2026-11-01',
            'to' => '2026-11-22',
            'metrics' => ['sales', 'received', 'result'],
            'chart' => 0,
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('Retirado para reinvestimento', $pdf);
        $this->assertStringContainsString('R$ 40,00', $pdf);
        $this->assertStringContainsString('R$ 60,00', $pdf);

        $withoutReinvestments = $this->get(route('nfc.report.export', [
            'from' => '2026-11-01',
            'to' => '2026-11-22',
            'metrics' => ['sales', 'received', 'result'],
            'chart' => 0,
            'reinvestments' => 0,
        ]))->assertOk()->getContent();
        $this->assertStringNotContainsString('Retirado para reinvestimento', $withoutReinvestments);
        $this->assertStringNotContainsString('MOVIMENTAÇÃO DO CAIXA NFC', $withoutReinvestments);
        $this->assertStringContainsString('R$ 60,00', $withoutReinvestments);

        $withReinvestments = $this->get(route('nfc.report.export', [
            'from' => '2026-11-01',
            'to' => '2026-11-22',
            'metrics' => ['received'],
            'chart' => 0,
            'reinvestments' => 1,
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('Retirado para reinvestimento', $withReinvestments);

        $this->getJson(route('nfc.report.export', [
            'from' => '2026-11-01',
            'to' => '2026-11-22',
            'metrics' => ['sales'],
            'reinvestments' => 'invalid',
        ]))->assertUnprocessable();

        $other = User::factory()->create();
        $this->actingAs($other)->delete(route('nfc.reinvestments.destroy', [
            'reinvestment' => $reinvestment->id,
        ]))->assertNotFound();
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.reinvested_cents', 0));

        $this->actingAs($owner)->delete(route('nfc.reinvestments.destroy', [
            'reinvestment' => $reinvestment->id,
        ]))->assertRedirect(route('nfc.index'));
        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.reinvested_cents', 0)
            ->where('summary.cash_available_cents', 10000)
            ->has('reinvestments', 0));
    }

    public function test_reinvestment_requires_received_cash_and_is_not_available_from_shared_links(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-22T15:00:00Z'));
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('nfc.reinvestments.store'), [
            'operation_id' => (string) Str::uuid(),
            'spent_on' => '2026-11-22',
            'amount_cents' => 1200,
            'description' => 'Compra de material',
        ])->assertSessionHasErrors('amount_cents');

        $this->post(route('nfc.share-links.store'), ['scope' => 'cud'])->assertRedirect();
        $sharedUrl = session('nfc_share_url');
        preg_match('~/compartilhado/([^/?]+)~', $sharedUrl, $matches);
        Auth::logout();

        $this->post(route('nfc.reinvestments.store'), [
            'operation_id' => (string) Str::uuid(),
            'spent_on' => '2026-11-22',
            'amount_cents' => 100,
            'description' => 'Sem sessão',
        ])->assertRedirect(route('login'));
        $this->post('/plaquinhas-nfc/compartilhado/'.$matches[1].'/reinvestimentos', [
            'amount_cents' => 100,
        ])->assertNotFound();

        $this->actingAs($owner)->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.reinvested_cents', 0)
            ->where('summary.cash_available_cents', 0));
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

    public function test_warranty_can_start_after_the_sale_without_shortening_the_term(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-22T15:00:00Z'));
        $company = $this->createCompany(User::factory()->create());
        $sale = $this->createSale($company, [
            'sold_on' => '2026-10-10',
            'warranty_starts_on' => '2026-10-20',
        ]);

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.sales.0.warranty_started_on', '2026-10-20')
            ->where('companies.0.sales.0.warranty_until', '2027-01-20'));

        $this->patch(route('nfc.sales.update', ['company' => $company->id, 'sale' => $sale['id']]), [
            'unit_id' => $sale['unit_id'],
            'sold_on' => '2026-10-10',
            'warranty_starts_on' => '2026-10-09',
            'total_cents' => 10000,
        ])->assertSessionHasErrors('warranty_starts_on');
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

    public function test_notice_creation_resolution_and_deletion_are_reflected_in_fresh_dashboard_data(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $operationId = (string) Str::uuid();

        $this->post(route('nfc.notices.store'), [
            'operation_id' => $operationId,
            'text' => 'Aviso de teste',
            'important' => false,
        ])->assertRedirect(route('nfc.index'));

        $notice = NfcRecord::query()->whereBelongsTo($owner)
            ->where('kind', NfcRecord::NOTICE)->where('record_key', $operationId)->firstOrFail();

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('notices', 1)
            ->where('notices.0.id', $notice->id)
            ->where('notices.0.text', 'Aviso de teste')
            ->where('notices.0.resolved_at', null));

        $this->patch(route('nfc.notices.resolve', ['notice' => $notice->id]))
            ->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('notices.0.resolved_at', fn ($value): bool => is_string($value) && $value !== ''));

        $this->delete(route('nfc.notices.destroy', ['notice' => $notice->id]))
            ->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('notices', 0)
            ->where('history.0.event', 'deleted')
            ->where('history.0.target_id', $notice->id));

        $this->assertNotNull($notice->fresh()->payload['deleted_at']);
        $this->delete(route('nfc.notices.destroy', ['notice' => $notice->id]))->assertNotFound();
    }

    public function test_shared_nfc_link_can_manage_notices_without_an_authenticated_session(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('nfc.share-links.store'), ['scope' => 'edit'])
            ->assertRedirect(route('nfc.index'));

        $url = session('nfc_share_url');
        $this->assertNotNull($url);
        preg_match('~/compartilhado/([^/?]+)~', $url, $matches);
        $token = $matches[1];

        Auth::logout();
        $operationId = (string) Str::uuid();
        $this->post(route('nfc.share.notices.store', ['token' => $token]), [
            'operation_id' => $operationId,
            'text' => 'Aviso via link',
            'important' => false,
        ])->assertRedirect(route('nfc.share.index', ['token' => $token]));

        $notice = NfcRecord::query()->whereBelongsTo($owner)
            ->where('kind', NfcRecord::NOTICE)->where('record_key', $operationId)->firstOrFail();

        $this->get(route('nfc.share.index', ['token' => $token]))->assertInertia(fn (Assert $page) => $page
            ->where('shared', true)
            ->has('notices', 1)
            ->where('notices.0.id', $notice->id));

        $this->delete(route('nfc.share.notices.destroy', ['token' => $token, 'notice' => $notice->id]))
            ->assertRedirect(route('nfc.share.index', ['token' => $token]));

        $this->get(route('nfc.share.index', ['token' => $token]))->assertInertia(fn (Assert $page) => $page
            ->has('notices', 0)
            ->has('history', 0));

        $this->actingAs($owner)->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('history.0.event', 'deleted')
            ->where('history.0.target_id', $notice->id));
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
        $other = User::factory()->create();
        $otherCompany = $this->createCompany($other, 'Outra empresa');
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->post(route('nfc.share-links.store'), ['scope' => 'edit'])->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        $this->assertNotNull($url);
        preg_match('~/compartilhado/([^/?]+)~', $url, $matches);
        $token = $matches[1];
        $link = NfcAccessLink::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertNotSame($token, $link->token_hash);

        Auth::logout();
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('Nfc/Index')
            ->where('shared', true)
            ->where('auth.user', null)
            ->has('companies', 1)
            ->where('companies.0.id', $company->id));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('nfc.companies.store'), [])->assertRedirect(route('login'));
        $this->patch(route('nfc.share.companies.update', [
            'token' => $token, 'company' => $otherCompany->id,
        ]), [
            'name' => 'Invasão', 'interested' => false,
        ])->assertNotFound();

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

        $this->actingAs($owner)->delete(route('nfc.share-links.destroy', ['link' => $link->id]))
            ->assertRedirect(route('nfc.index'));
        Auth::logout();
        $this->get($url)->assertNotFound();
        $this->get(route('nfc.share.index', ['token' => str_repeat('a', 64)]))->assertNotFound();
    }

    public function test_view_share_link_cannot_mutate_nfc_records(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('nfc.share-links.store'))
            ->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        preg_match('~/compartilhado/([^/?]+)~', $url, $matches);
        $token = $matches[1];

        Auth::logout();
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('read_only', true)
            ->has('history', 0));
        $this->post(route('nfc.share.companies.store', ['token' => $token]), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Bloqueado',
            'interested' => true,
        ])->assertForbidden();
        $this->delete(route('nfc.share.companies.destroy', ['token' => $token, 'company' => 1]))
            ->assertForbidden();
        $this->assertDatabaseMissing('nfc_records', ['kind' => NfcRecord::COMPANY, 'user_id' => $owner->id]);
    }

    public function test_link_permissions_are_independent_and_only_owner_can_change_them(): void
    {
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->post(route('nfc.share-links.store'), ['scope' => 'cu'])->assertRedirect();
        $firstUrl = session('nfc_share_url');
        $first = NfcAccessLink::query()->whereBelongsTo($owner)->latest('id')->firstOrFail();
        $this->post(route('nfc.share-links.store'), ['scope' => 'view'])->assertRedirect();
        $secondUrl = session('nfc_share_url');
        $second = NfcAccessLink::query()->whereBelongsTo($owner)->latest('id')->firstOrFail();
        $firstToken = basename($firstUrl);
        $secondToken = basename($secondUrl);
        $update = route('nfc.share-links.update', ['link' => $first->id]);

        Auth::logout();
        $this->get($firstUrl)->assertInertia(fn (Assert $page) => $page
            ->where('permissions.create', true)
            ->where('permissions.update', true)
            ->where('permissions.delete', false)
            ->where('privacy_key', 'link-'.$first->id));
        $this->get($secondUrl)->assertInertia(fn (Assert $page) => $page
            ->where('permissions.create', false)
            ->where('privacy_key', 'link-'.$second->id));
        $this->delete(route('nfc.share.companies.destroy', ['token' => $firstToken, 'company' => $company->id]))->assertForbidden();
        $this->patch($update, ['scope' => 'cud'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($update, ['scope' => 'cud'])->assertNotFound();
        $this->actingAs($owner)->patch($update, ['scope' => 'd'])->assertRedirect(route('nfc.index'));

        Auth::logout();
        $this->post(route('nfc.share.companies.store', ['token' => $firstToken]), [])->assertForbidden();
        $this->patch(route('nfc.share.companies.update', ['token' => $firstToken, 'company' => $company->id]), [])->assertForbidden();
        $this->delete(route('nfc.share.companies.destroy', ['token' => $secondToken, 'company' => $company->id]))->assertForbidden();
        $this->delete(route('nfc.share.companies.destroy', ['token' => $firstToken, 'company' => $company->id]))->assertRedirect();
        $this->assertSame('view', $second->fresh()->scope);
    }

    public function test_expired_nfc_share_token_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('nfc.share-links.store'), ['scope' => 'edit'])
            ->assertRedirect(route('nfc.index'));
        $url = session('nfc_share_url');
        NfcAccessLink::query()->where('user_id', $owner->id)->firstOrFail()
            ->update(['expires_at' => now()->subSecond()]);
        Auth::logout();
        $this->get($url)->assertNotFound();
    }

    public function test_pdf_never_generates_the_blank_comparative_columns_page(): void
    {
        $owner = User::factory()->create();
        $params = ['from' => '2026-11-01', 'to' => '2026-11-22', 'metrics' => ['received'], 'chart' => 1, 'name' => 'Vendas Novembro NFC'];
        $response = $this->actingAs($owner)->get(route('nfc.report.export', $params))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="vendas-novembro-nfc.pdf"');

        $empty = $response->getContent();
        $this->assertSame(2, preg_match_all('/\/Type \/Page\b/', $empty));
        $this->assertStringNotContainsString('Comparativos  /  colunas verticais', $empty);
        $this->assertStringNotContainsString('VALORES FINANCEIROS', $empty);
        $this->assertStringNotContainsString('INDICADORES OPERACIONAIS', $empty);
        $this->assertStringNotContainsString('COMPOSI', $empty);
        $this->assertStringNotContainsString('EVOLU', $empty);
        $this->assertStringContainsString('Vendas Novembro NFC', $empty);

        $company = $this->createCompany($owner);
        $this->createSale($company, [
            'sold_on' => '2026-11-10',
            'payments' => [['method' => 'pix', 'amount_cents' => 4000, 'received_on' => '2026-11-12']],
        ]);

        foreach ([['received'], ['sold'], ['sales', 'sold']] as $metrics) {
            $pdf = $this->get(route('nfc.report.export', array_replace($params, ['metrics' => $metrics])))
                ->assertOk()->getContent();
            // Somente painel, detalhamento e tabela por empresa: nenhuma página de comparativos.
            $this->assertSame(3, preg_match_all('/\/Type \/Page\b/', $pdf));
            $this->assertStringNotContainsString('Comparativos  /  colunas verticais', $pdf);
            $this->assertStringNotContainsString('VALORES FINANCEIROS', $pdf);
            $this->assertStringNotContainsString('INDICADORES OPERACIONAIS', $pdf);
            $this->assertStringContainsString('COMPOSI', $pdf);
            $this->assertStringContainsString('EVOLU', $pdf);
        }

        $withoutGraph = $this->get(route('nfc.report.export', array_replace($params, ['metrics' => ['sold'], 'chart' => 0])))
            ->assertOk()->getContent();
        $this->assertSame(3, preg_match_all('/\/Type \/Page\b/', $withoutGraph));
        $this->assertStringNotContainsString('EVOLU', $withoutGraph);

        $this->getJson(route('nfc.report.export', array_replace($params, ['name' => '../indevido'])))
            ->assertUnprocessable();
    }

    public function test_report_pdf_filters_metrics_dates_and_owner_and_is_not_available_to_shared_links(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-22T15:00:00Z'));
        $owner = User::factory()->create();
        $company = $this->createCompany($owner);
        $this->createSale($company, [
            'sold_on' => '2026-11-10',
            'payments' => [['method' => 'pix', 'amount_cents' => 4000, 'received_on' => '2026-11-12']],
        ]);
        $this->createSale($company, [
            'sold_on' => '2026-10-10',
            'payments' => [['method' => 'cash', 'amount_cents' => 7000, 'received_on' => '2026-10-12']],
        ]);
        $other = $this->createCompany(User::factory()->create());
        $this->createSale($other, [
            'sold_on' => '2026-11-10',
            'payments' => [['method' => 'pix', 'amount_cents' => 9000, 'received_on' => '2026-11-12']],
        ]);

        $params = ['from' => '2026-11-01', 'to' => '2026-11-22', 'metrics' => ['received'], 'chart' => 0];
        $url = route('nfc.report.export', $params);
        $response = $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('R$ 40,00', $response->getContent());
        $this->assertStringNotContainsString('R$ 70,00', $response->getContent());
        $this->assertStringNotContainsString('R$ 90,00', $response->getContent());
        $this->assertStringNotContainsString('Valor vendido', $response->getContent());
        $this->getJson(route('nfc.report.export', array_replace($params, ['metrics' => ['invalid']])))->assertUnprocessable();

        $this->post(route('nfc.share-links.store'), ['scope' => 'cud'])->assertRedirect();
        $shared = session('nfc_share_url');
        Auth::logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->get($shared.'/relatorio.pdf?'.http_build_query($params))->assertNotFound();
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
