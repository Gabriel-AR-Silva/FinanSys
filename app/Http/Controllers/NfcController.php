<?php

namespace App\Http\Controllers;

use App\Actions\ManageNfcRecords;
use App\Models\NfcAccessLink;
use App\Models\NfcRecord;
use App\Models\User;
use App\Queries\NfcDashboardQuery;
use App\Queries\NfcReportQuery;
use App\Support\NfcReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NfcController extends Controller
{
    private const OPERATION_RULES = ['required', 'uuid'];

    private const NAME_RULES = ['required', 'string', 'max:160'];

    private const AMOUNT_RULES = ['required', 'integer', 'min:1', 'max:999999999'];

    public function index(Request $request, NfcDashboardQuery $query): Response
    {
        $shared = $request->attributes->has('nfc_share_owner');
        $linksAvailable = Schema::hasTable('nfc_access_links');
        $scopesAvailable = $linksAvailable && Schema::hasColumn('nfc_access_links', 'scope');

        $permissions = $shared
            ? $request->attributes->get('nfc_share_permissions', ['create' => false, 'update' => false, 'delete' => false])
            : ['create' => true, 'update' => true, 'delete' => true];

        return Inertia::render('Nfc/Index', [
            ...$query->forUser($this->owner($request), ! $shared),
            'shared' => $shared,
            'read_only' => ! in_array(true, $permissions, true),
            'permissions' => $permissions,
            'privacy_key' => $shared ? 'link-'.$request->attributes->get('nfc_share_link')->id : 'owner-'.$request->user()->id,
            'share_token' => $shared ? (string) $request->attributes->get('nfc_share_token') : null,
            'created_share_url' => $shared ? null : $request->session()->get('nfc_share_url'),
            'share_links_enabled' => ! $shared && $scopesAvailable,
            'nfc_created_record' => $request->session()->get('nfc_created_record'),
            'share_links' => $shared || ! $linksAvailable ? [] : NfcAccessLink::query()
                ->whereBelongsTo($request->user())
                ->latest()->limit(20)
                ->get($scopesAvailable ? ['id', 'expires_at', 'revoked_at', 'created_at', 'scope'] : ['id', 'expires_at', 'revoked_at', 'created_at'])
                ->toArray(),
        ]);
    }

    public function exportReport(Request $request, NfcDashboardQuery $dashboard, NfcReportQuery $query, NfcReportPdf $pdf): \Illuminate\Http\Response
    {
        abort_if($request->attributes->has('nfc_share_owner'), 403);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'metrics' => ['required', 'array', 'list', 'min:1', 'max:9'],
            'metrics.*' => ['required', 'distinct', Rule::in(NfcReportQuery::METRICS)],
            'chart' => ['sometimes', 'boolean'],
        ]);
        $report = $query->calculate($dashboard->forUser($user, false), $data['from'], $data['to']);

        return response($pdf->render($report, $data['metrics'], $data['from'], $data['to'], $request->boolean('chart')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nfc-relatorio-'.$data['from'].'-'.$data['to'].'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_if($request->attributes->has('nfc_share_owner'), 403);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate(['confirmation' => ['required', 'in:ZERAR']]);

        DB::transaction(function () use ($user): void {
            NfcRecord::query()->whereBelongsTo($user)->delete();
        }, 3);

        // Private images are no longer addressable after their records are removed.
        Storage::disk('local')->deleteDirectory("nfc/manufacturers/{$user->id}");

        return to_route('nfc.index', status: 303);
    }

    public function storeCompany(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate([
            ...$this->companyRules(),
            'operation_id' => self::OPERATION_RULES,
            'unit_name' => ['nullable', 'string', 'max:160'],
        ]);

        $records->storeCompany($this->owner($request), $data);

        return $this->redirectToDashboard($request);
    }

    public function updateCompany(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $records->updateCompany($this->owner($request), $company, $request->validate($this->companyRules()));

        return $this->redirectToDashboard($request);
    }

    public function storeUnit(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => self::OPERATION_RULES,
            'name' => self::NAME_RULES,
            'production_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $records->storeUnit($this->owner($request), $company, $data);

        return $this->redirectToDashboard($request);
    }

    public function updateUnit(Request $request, ManageNfcRecords $records, int $company, string $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => self::NAME_RULES,
            'production_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $records->updateUnit($this->owner($request), $company, $unit, $data);

        return $this->redirectToDashboard($request);
    }

    public function storeSale(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $data = $request->validate([
            ...$this->saleRules(),
            'operation_id' => self::OPERATION_RULES,
            'standard_quantity' => ['required', 'integer', 'min:0', 'max:1000'],
            'custom_quantity' => ['required', 'integer', 'min:0', 'max:1000'],
            'in_customization_quantity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'payments' => ['sometimes', 'array', 'list', 'max:100'],
            'payments.*' => ['array:method,amount_cents,received_on'],
            'payments.*.method' => ['required', Rule::in(ManageNfcRecords::PAYMENT_METHODS)],
            'payments.*.amount_cents' => self::AMOUNT_RULES,
            'payments.*.received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
        ]);

        $records->storeSale($this->owner($request), $company, $data);

        return $this->redirectToDashboard($request);
    }

    public function updateSale(Request $request, ManageNfcRecords $records, int $company, string $sale): RedirectResponse
    {
        $records->updateSale($this->owner($request), $company, $sale, $request->validate($this->saleRules()));

        return $this->redirectToDashboard($request);
    }

    public function storePayment(Request $request, ManageNfcRecords $records, int $company, string $sale): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => self::OPERATION_RULES,
            'method' => ['required', Rule::in(ManageNfcRecords::PAYMENT_METHODS)],
            'amount_cents' => self::AMOUNT_RULES,
            'received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
        ]);

        $records->storePayment($this->owner($request), $company, $sale, $data);

        return $this->redirectToDashboard($request);
    }

    public function updatePlate(Request $request, ManageNfcRecords $records, int $company, string $sale, string $plate): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ManageNfcRecords::PLATE_TYPES)],
            'label' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customizing' => ['required', 'boolean'],
            'defective' => ['required', 'boolean'],
            'estimated_loss_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $records->updatePlate($this->owner($request), $company, $sale, $plate, $data);

        return $this->redirectToDashboard($request);
    }

    public function storeProduction(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate([
            ...$this->productionRules(),
            'operation_id' => self::OPERATION_RULES,
            'initial' => ['required', 'boolean'],
        ]);

        $record = $records->storeProduction($this->owner($request), $data);

        return $this->redirectToDashboard($request)->with('nfc_created_record', ['kind' => 'production', 'id' => $record->id, 'operation_id' => $data['operation_id']]);
    }

    public function updateProduction(Request $request, ManageNfcRecords $records, int $production): RedirectResponse
    {
        $records->updateProduction($this->owner($request), $production, $request->validate($this->productionRules()));

        return $this->redirectToDashboard($request);
    }

    public function storeNotice(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => self::OPERATION_RULES,
            'text' => ['required', 'string', 'max:2000'],
            'important' => ['required', 'boolean'],
            'company_id' => ['nullable', 'required_with:plate_id', 'integer', 'min:1'],
            'plate_id' => ['nullable', 'uuid'],
        ]);

        $records->storeNotice($this->owner($request), $data);

        return $this->redirectToDashboard($request);
    }

    public function resolveNotice(Request $request, ManageNfcRecords $records, int $notice): RedirectResponse
    {
        $records->resolveNotice($this->owner($request), $notice);

        return $this->redirectToDashboard($request);
    }

    public function deleteCompany(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $records->deleteRecord($this->owner($request), $company, NfcRecord::COMPANY);

        return $this->redirectToDashboard($request);
    }

    public function deleteUnit(Request $request, ManageNfcRecords $records, int $company, string $unit): RedirectResponse
    {
        $records->deleteUnit($this->owner($request), $company, $unit);

        return $this->redirectToDashboard($request);
    }

    public function deleteSale(Request $request, ManageNfcRecords $records, int $company, string $sale): RedirectResponse
    {
        $records->deleteSale($this->owner($request), $company, $sale);

        return $this->redirectToDashboard($request);
    }

    public function deletePayment(Request $request, ManageNfcRecords $records, int $company, string $sale, string $payment): RedirectResponse
    {
        $records->deletePayment($this->owner($request), $company, $sale, $payment);

        return $this->redirectToDashboard($request);
    }

    public function deletePlate(Request $request, ManageNfcRecords $records, int $company, string $sale, string $plate): RedirectResponse
    {
        $records->deletePlate($this->owner($request), $company, $sale, $plate);

        return $this->redirectToDashboard($request);
    }

    public function deleteProduction(Request $request, ManageNfcRecords $records, int $production): RedirectResponse
    {
        $records->deleteProduction($this->owner($request), $production);

        return $this->redirectToDashboard($request);
    }

    public function deleteNotice(Request $request, ManageNfcRecords $records, int $notice): RedirectResponse
    {
        $records->deleteRecord($this->owner($request), $notice, NfcRecord::NOTICE);

        return $this->redirectToDashboard($request);
    }

    private function owner(Request $request): User
    {
        return $request->attributes->get('nfc_share_owner') ?? $request->user();
    }

    private function redirectToDashboard(Request $request): RedirectResponse
    {
        return $request->attributes->has('nfc_share_owner')
            ? to_route('nfc.share.index', ['token' => $request->attributes->get('nfc_share_token')])
            : to_route('nfc.index');
    }

    private function companyRules(): array
    {
        return [
            'name' => self::NAME_RULES,
            'phone' => ['nullable', 'string', 'max:80'],
            'cnpj' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'interested' => ['required', 'boolean'],
        ];
    }

    private function saleRules(): array
    {
        return [
            'unit_id' => ['required', 'uuid'],
            'sold_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
            'warranty_starts_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sold_on', 'before_or_equal:'.$this->today()],
            'total_cents' => self::AMOUNT_RULES,
        ];
    }

    private function productionRules(): array
    {
        return [
            'produced_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'customized_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'cost_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'manufacturer_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['in_progress', 'completed'])],
        ];
    }

    private function today(): string
    {
        return now('America/Sao_Paulo')->toDateString();
    }
}
