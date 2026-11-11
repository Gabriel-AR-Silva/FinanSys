<?php

namespace App\Actions;

use App\Models\NfcRecord;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageNfcRecords
{
    private const INITIAL_PRODUCTION_KEY = '00000000-0000-4000-8000-000000000000';

    public const PAYMENT_METHODS = ['cash', 'pix', 'debit', 'credit'];

    public const PLATE_TYPES = ['standard', 'custom'];

    public function storeCompany(User $user, array $data): void
    {
        $this->createRecord($user, NfcRecord::COMPANY, $data['operation_id'], [
            ...$this->companyFields($data),
            'phone' => $data['phone'] ?? null,
            'cnpj' => $data['cnpj'] ?? null,
            'notes' => $data['notes'] ?? null,
            'units' => [[
                'id' => (string) Str::uuid(),
                'name' => $data['unit_name'] ?? 'Unidade 1',
            ]],
            'sales' => [],
        ]);
    }

    public function updateCompany(User $user, int $company, array $data): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, fn (array $payload): array => [
            ...$payload,
            ...$this->companyFields($data),
        ]);
    }

    public function storeUnit(User $user, int $company, array $data): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($data): array {
            $unitId = strtolower($data['operation_id']);

            if ($this->indexOf($payload['units'], $unitId) === null) {
                $payload['units'][] = ['id' => $unitId, 'name' => $data['name']];
            }

            return $payload;
        });
    }

    public function updateUnit(User $user, int $company, string $unit, string $name): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($unit, $name): array {
            $index = $this->indexOrFail($payload['units'], $unit);
            $payload['units'][$index]['name'] = $name;

            return $payload;
        });
    }

    public function storeSale(User $user, int $company, array $data): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($data): array {
            $saleId = strtolower($data['operation_id']);

            if ($this->indexOf($payload['sales'], $saleId) !== null) {
                return $payload;
            }

            $sale = $this->saleFields($payload['units'], $data);
            $standardQuantity = (int) $data['standard_quantity'];
            $customQuantity = (int) $data['custom_quantity'];
            $inCustomization = (int) ($data['in_customization_quantity'] ?? 0);

            if ($standardQuantity + $customQuantity === 0) {
                throw ValidationException::withMessages(['standard_quantity' => 'Informe pelo menos uma plaquinha.']);
            }

            if ($inCustomization > $customQuantity) {
                throw ValidationException::withMessages([
                    'in_customization_quantity' => 'A quantidade em customização não pode exceder a quantidade personalizada.',
                ]);
            }

            $payments = array_map(
                fn (array $payment): array => $this->paymentFields($payment, (string) Str::uuid()),
                $data['payments'] ?? [],
            );

            if ($this->receivedCents($payments) > $sale['total_cents']) {
                throw ValidationException::withMessages(['payments' => 'Os pagamentos não podem ultrapassar o total da venda.']);
            }

            $plates = [];

            for ($number = 1; $number <= $standardQuantity; $number++) {
                $plates[] = $this->newPlate('standard', "Normal {$number}");
            }

            for ($number = 1; $number <= $customQuantity; $number++) {
                $plates[] = $this->newPlate('custom', "Personalizada {$number}", $number <= $inCustomization);
            }

            $payload['sales'][] = [
                'id' => $saleId,
                ...$sale,
                'plates' => $plates,
                'payments' => $payments,
            ];

            return $payload;
        });
    }

    public function updateSale(User $user, int $company, string $sale, array $data): void
    {
        $this->changeSale($user, $company, $sale, function (array $stored, array $units) use ($data): array {
            $changes = $this->saleFields($units, $data);

            if ($changes['total_cents'] < $this->receivedCents($stored['payments'])) {
                throw ValidationException::withMessages(['total_cents' => 'O total da venda não pode ser menor que o valor já recebido.']);
            }

            return [...$stored, ...$changes];
        });
    }

    public function storePayment(User $user, int $company, string $sale, array $data): void
    {
        $this->changeSale($user, $company, $sale, function (array $stored) use ($data): array {
            $payment = $this->paymentFields($data, strtolower($data['operation_id']));
            $index = $this->indexOf($stored['payments'], $payment['id']);

            if ($index !== null) {
                if ($stored['payments'][$index] != $payment) {
                    throw ValidationException::withMessages(['amount_cents' => 'Este pagamento já foi registrado com outros dados.']);
                }

                return $stored;
            }

            if ($this->receivedCents($stored['payments']) + $payment['amount_cents'] > $stored['total_cents']) {
                throw ValidationException::withMessages(['amount_cents' => 'O pagamento não pode ultrapassar o saldo da venda.']);
            }

            $stored['payments'][] = $payment;

            return $stored;
        });
    }

    public function updatePlate(User $user, int $company, string $sale, string $plate, array $data): void
    {
        $this->changeSale($user, $company, $sale, function (array $stored) use ($plate, $data): array {
            $index = $this->indexOrFail($stored['plates'], $plate);
            $changes = Arr::only($data, ['label', 'notes', 'type']);
            $changes['customizing'] = $data['type'] === 'custom' && (bool) $data['customizing'];

            $stored['plates'][$index] = [...$stored['plates'][$index], ...$changes];

            return $stored;
        });
    }

    public function storeProduction(User $user, array $data): void
    {
        $initial = (bool) $data['initial'];
        $payload = $this->productionFields($data);
        $record = $this->createRecord(
            $user,
            $initial ? NfcRecord::INITIAL_PRODUCTION : NfcRecord::PRODUCTION,
            $initial ? self::INITIAL_PRODUCTION_KEY : $data['operation_id'],
            $payload,
        );

        if ($initial && $record->payload != $payload) {
            throw ValidationException::withMessages([
                'quantity' => 'O lote inicial já foi registrado. Abra a fabricação para editar os dados.',
            ]);
        }
    }

    public function updateProduction(User $user, int $production, array $data): void
    {
        $this->changeRecord(
            $user,
            $production,
            [NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION],
            fn (array $payload): array => $this->productionFields([...$payload, ...$data]),
        );
    }

    public function storeNotice(User $user, array $data): void
    {
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $plateId = isset($data['plate_id']) ? strtolower($data['plate_id']) : null;

        if ($companyId !== null) {
            $company = NfcRecord::query()->whereBelongsTo($user)
                ->where('kind', NfcRecord::COMPANY)
                ->find($companyId);

            if ($company === null) {
                throw ValidationException::withMessages(['company_id' => 'Selecione uma empresa válida.']);
            }

            if ($plateId !== null && ! $this->companyHasPlate($company->payload, $plateId)) {
                throw ValidationException::withMessages(['plate_id' => 'Selecione uma plaquinha dessa empresa.']);
            }
        }

        $this->createRecord($user, NfcRecord::NOTICE, $data['operation_id'], [
            'text' => $data['text'],
            'important' => (bool) $data['important'],
            'company_id' => $companyId,
            'plate_id' => $plateId,
            'resolved_at' => null,
        ]);
    }

    public function resolveNotice(User $user, int $notice): void
    {
        $this->changeRecord($user, $notice, NfcRecord::NOTICE, function (array $payload): array {
            $payload['resolved_at'] ??= now()->toISOString();

            return $payload;
        });
    }

    private function createRecord(User $user, string $kind, string $key, array $payload): NfcRecord
    {
        return NfcRecord::query()->firstOrCreate([
            'user_id' => $user->id,
            'kind' => $kind,
            'record_key' => strtolower($key),
        ], ['payload' => $payload]);
    }

    private function changeRecord(User $user, int $id, string|array $kinds, Closure $change): void
    {
        DB::transaction(function () use ($user, $id, $kinds, $change): void {
            $record = NfcRecord::query()->whereBelongsTo($user)->whereIn('kind', (array) $kinds)
                ->lockForUpdate()->findOrFail($id);

            $record->update(['payload' => $change($record->payload)]);
        }, 3);
    }

    private function changeSale(User $user, int $company, string $sale, Closure $change): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($sale, $change): array {
            $index = $this->indexOrFail($payload['sales'], $sale);
            $payload['sales'][$index] = $change($payload['sales'][$index], $payload['units']);

            return $payload;
        });
    }

    private function companyFields(array $data): array
    {
        return [
            ...Arr::only($data, ['name', 'phone', 'cnpj', 'notes']),
            'interested' => (bool) $data['interested'],
        ];
    }

    private function saleFields(array $units, array $data): array
    {
        if ($this->indexOf($units, $data['unit_id']) === null) {
            throw ValidationException::withMessages(['unit_id' => 'Selecione uma unidade dessa empresa.']);
        }

        return [
            'unit_id' => strtolower($data['unit_id']),
            'sold_on' => $data['sold_on'],
            'total_cents' => (int) $data['total_cents'],
        ];
    }

    private function productionFields(array $data): array
    {
        $quantity = (int) $data['quantity'];
        $customizedQuantity = isset($data['customized_quantity']) ? (int) $data['customized_quantity'] : null;

        if ($customizedQuantity !== null && $customizedQuantity > $quantity) {
            throw ValidationException::withMessages([
                'customized_quantity' => 'A quantidade personalizada não pode exceder a quantidade fabricada.',
            ]);
        }

        return [
            'produced_on' => $data['produced_on'] ?? null,
            'quantity' => $quantity,
            'customized_quantity' => $customizedQuantity,
            'cost_cents' => isset($data['cost_cents']) ? (int) $data['cost_cents'] : null,
        ];
    }

    private function newPlate(string $type, string $label, bool $customizing = false): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'label' => $label,
            'notes' => null,
            'customizing' => $customizing,
        ];
    }

    private function paymentFields(array $data, string $id): array
    {
        return ['id' => $id, 'method' => $data['method'], 'amount_cents' => (int) $data['amount_cents']];
    }

    private function receivedCents(array $payments): int
    {
        return array_sum(array_column($payments, 'amount_cents'));
    }

    private function companyHasPlate(array $payload, string $id): bool
    {
        foreach ($payload['sales'] as $sale) {
            if ($this->indexOf($sale['plates'], $id) !== null) {
                return true;
            }
        }

        return false;
    }

    private function indexOf(array $items, string $id): ?int
    {
        $id = strtolower($id);

        foreach ($items as $index => $item) {
            if ($item['id'] === $id) {
                return $index;
            }
        }

        return null;
    }

    private function indexOrFail(array $items, string $id): int
    {
        $index = $this->indexOf($items, $id);
        abort_if($index === null, 404);

        return $index;
    }
}
