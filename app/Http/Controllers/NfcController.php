<?php

namespace App\Http\Controllers;

use App\Actions\ManageNfcRecords;
use App\Models\NfcAccessLink;
use App\Models\User;
use App\Queries\NfcDashboardQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Nfc/Index', [
            ...$query->forUser($this->owner($request)),
            'shared' => $shared,
            'share_token' => $shared ? (string) $request->route('token') : null,
            'created_share_url' => $shared ? null : $request->session()->get('nfc_share_url'),
            'share_links' => $shared ? [] : NfcAccessLink::query()
                ->whereBelongsTo($request->user())
                ->latest()->limit(20)
                ->get(['id', 'expires_at', 'revoked_at', 'created_at'])
                ->toArray(),
        ]);
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
            'payments.*' => ['array:method,amount_cents'],
            'payments.*.method' => ['required', Rule::in(ManageNfcRecords::PAYMENT_METHODS)],
            'payments.*.amount_cents' => self::AMOUNT_RULES,
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

        $records->storeProduction($this->owner($request), $data);

        return $this->redirectToDashboard($request);
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
        $records->deleteRecord($this->owner($request), $company, \App\Models\NfcRecord::COMPANY);

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
        $records->deleteRecord($this->owner($request), $notice, \App\Models\NfcRecord::NOTICE);

        return $this->redirectToDashboard($request);
    }

    private function owner(Request $request): User
    {
        return $request->attributes->get('nfc_share_owner') ?? $request->user();
    }

    private function redirectToDashboard(Request $request): RedirectResponse
    {
        return $request->attributes->has('nfc_share_owner')
            ? to_route('nfc.share.index', ['token' => $request->route('token')])
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
            'status' => ['sometimes', Rule::in(['in_progress', 'completed'])],
        ];
    }

    private function today(): string
    {
        return now('America/Sao_Paulo')->toDateString();
    }
}
