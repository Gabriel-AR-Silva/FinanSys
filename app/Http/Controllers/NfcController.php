<?php

namespace App\Http\Controllers;

use App\Actions\ManageNfcRecords;
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
        return Inertia::render('Nfc/Index', $query->forUser($request->user()));
    }

    public function storeCompany(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate([
            ...$this->companyRules(),
            'operation_id' => self::OPERATION_RULES,
            'unit_name' => ['nullable', 'string', 'max:160'],
        ]);

        $records->storeCompany($request->user(), $data);

        return to_route('nfc.index');
    }

    public function updateCompany(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $records->updateCompany($request->user(), $company, $request->validate($this->companyRules()));

        return to_route('nfc.index');
    }

    public function storeUnit(Request $request, ManageNfcRecords $records, int $company): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => self::OPERATION_RULES,
            'name' => self::NAME_RULES,
            'production_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $records->storeUnit($request->user(), $company, $data);

        return to_route('nfc.index');
    }

    public function updateUnit(Request $request, ManageNfcRecords $records, int $company, string $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => self::NAME_RULES,
            'production_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $records->updateUnit($request->user(), $company, $unit, $data);

        return to_route('nfc.index');
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

        $records->storeSale($request->user(), $company, $data);

        return to_route('nfc.index');
    }

    public function updateSale(Request $request, ManageNfcRecords $records, int $company, string $sale): RedirectResponse
    {
        $records->updateSale($request->user(), $company, $sale, $request->validate($this->saleRules()));

        return to_route('nfc.index');
    }

    public function storePayment(Request $request, ManageNfcRecords $records, int $company, string $sale): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => self::OPERATION_RULES,
            'method' => ['required', Rule::in(ManageNfcRecords::PAYMENT_METHODS)],
            'amount_cents' => self::AMOUNT_RULES,
        ]);

        $records->storePayment($request->user(), $company, $sale, $data);

        return to_route('nfc.index');
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

        $records->updatePlate($request->user(), $company, $sale, $plate, $data);

        return to_route('nfc.index');
    }

    public function storeProduction(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate([
            ...$this->productionRules(),
            'operation_id' => self::OPERATION_RULES,
            'initial' => ['required', 'boolean'],
        ]);

        $records->storeProduction($request->user(), $data);

        return to_route('nfc.index');
    }

    public function updateProduction(Request $request, ManageNfcRecords $records, int $production): RedirectResponse
    {
        $records->updateProduction($request->user(), $production, $request->validate($this->productionRules()));

        return to_route('nfc.index');
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

        $records->storeNotice($request->user(), $data);

        return to_route('nfc.index');
    }

    public function resolveNotice(Request $request, ManageNfcRecords $records, int $notice): RedirectResponse
    {
        $records->resolveNotice($request->user(), $notice);

        return to_route('nfc.index');
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
