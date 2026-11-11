<?php

namespace App\Actions;

use App\Models\NfcAccessLink;
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

    public function storeManufacturer(User $user, array $data): void
    {
        $this->createRecord($user, NfcRecord::MANUFACTURER, $data['operation_id'], [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'images' => [],
        ]);
    }

    public function updateManufacturer(User $user, int $manufacturer, array $data): void
    {
        $this->changeRecord($user, $manufacturer, NfcRecord::MANUFACTURER, fn (array $payload): array => [
            ...$payload,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function deleteManufacturer(User $user, int $manufacturer): array
    {
        $record = NfcRecord::query()->whereBelongsTo($user)
            ->where('kind', NfcRecord::MANUFACTURER)->findOrFail($manufacturer);
        abort_if(! empty($record->payload['deleted_at']), 404);

        $linked = NfcRecord::query()->whereBelongsTo($user)
            ->whereIn('kind', [NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION])
            ->get()->contains(fn (NfcRecord $batch): bool => empty($batch->payload['deleted_at'])
                && (int) ($batch->payload['manufacturer_id'] ?? 0) === $manufacturer);

        if ($linked) {
            throw ValidationException::withMessages(['manufacturer' => 'Desvincule os lotes antes de excluir este fabricante.']);
        }

        $paths = array_column($record->payload['images'] ?? [], 'path');
        $this->deleteRecord($user, $manufacturer, NfcRecord::MANUFACTURER);

        return $paths;
    }

    public function attachManufacturerImages(User $user, int $manufacturer, array $images): void
    {
        $this->changeRecord($user, $manufacturer, NfcRecord::MANUFACTURER, function (array $payload) use ($images): array {
            $existing = $payload['images'] ?? [];

            if (count($existing) + count($images) > 10) {
                throw ValidationException::withMessages(['images' => 'Cada fabricante aceita no máximo 10 imagens.']);
            }

            $payload['images'] = [...$existing, ...$images];

            return $payload;
        });
    }

    public function removeManufacturerImage(User $user, int $manufacturer, string $image): string
    {
        $removedPath = null;
        $this->changeRecord($user, $manufacturer, NfcRecord::MANUFACTURER, function (array $payload) use ($image, &$removedPath): array {
            $index = $this->indexOrFail($payload['images'] ?? [], $image);
            $removedPath = $payload['images'][$index]['path'];
            array_splice($payload['images'], $index, 1);

            return $payload;
        });

        return $removedPath;
    }

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
                'production_id' => null,
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
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($data, $user): array {
            $unitId = strtolower($data['operation_id']);

            if ($this->indexOf($payload['units'], $unitId) === null) {
                $payload['units'][] = [
                    'id' => $unitId,
                    'name' => $data['name'],
                    'production_id' => $this->validatedProductionId($user, $data['production_id'] ?? null),
                ];
            }

            return $payload;
        });
    }

    public function updateUnit(User $user, int $company, string $unit, array $data): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($user, $unit, $data): array {
            $index = $this->indexOrFail($payload['units'], $unit);
            $payload['units'][$index]['name'] = $data['name'];
            $payload['units'][$index]['production_id'] = $this->validatedProductionId($user, $data['production_id'] ?? null);

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
            $changes['defective'] = (bool) $data['defective'];
            $changes['estimated_loss_cents'] = $changes['defective'] ? ($data['estimated_loss_cents'] ?? null) : null;

            $stored['plates'][$index] = [...$stored['plates'][$index], ...$changes];

            return $stored;
        });
    }

    public function storeProduction(User $user, array $data): void
    {
        $initial = (bool) $data['initial'];
        $payload = $this->productionFields($data);
        $payload['manufacturer_id'] = $this->validatedManufacturerId($user, $data['manufacturer_id'] ?? null);
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
            fn (array $payload): array => [
                ...$this->productionFields([...$payload, ...$data]),
                'manufacturer_id' => $this->validatedManufacturerId($user, $data['manufacturer_id'] ?? $payload['manufacturer_id'] ?? null),
            ],
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

            if ($company === null || ! empty($company->payload['deleted_at'])) {
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
            $payload['resolved_at'] = $payload['resolved_at'] ? null : now()->toISOString();

            return $payload;
        });
    }

    public function deleteRecord(User $user, int $id, string|array $kinds): void
    {
        $this->changeRecord($user, $id, $kinds, fn (array $payload): array => [
            ...$payload, 'deleted_at' => now()->toISOString(),
        ], 'deleted');
    }

    public function deleteProduction(User $user, int $production): void
    {
        $companies = NfcRecord::query()->whereBelongsTo($user)
            ->where('kind', NfcRecord::COMPANY)->get();
        foreach ($companies as $company) {
            if (! empty($company->payload['deleted_at'])) {
                continue;
            }
            foreach ($company->payload['units'] as $unit) {
                if (($unit['production_id'] ?? null) === $production) {
                    throw ValidationException::withMessages(['production' => 'Desvincule o lote das unidades antes de excluí-lo.']);
                }
            }
        }

        $this->deleteRecord($user, $production, [NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION]);
    }

    public function recordAccessEvent(User $user, string $event, NfcAccessLink $link): void
    {
        NfcRecord::query()->create([
            'user_id' => $user->id,
            'kind' => NfcRecord::HISTORY,
            'record_key' => (string) Str::uuid(),
            'payload' => [
                'event' => $event,
                'target_kind' => 'share_link',
                'target_id' => $link->id,
                'label' => 'Link de acesso NFC',
                'actor' => $user->name,
                'occurred_at' => now()->toISOString(),
                'before' => [],
                'after' => ['expires_at' => $link->expires_at->toISOString(), 'revoked' => $link->revoked_at !== null],
            ],
        ]);
    }

    public function deleteUnit(User $user, int $company, string $unit): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($unit): array {
            $this->indexOrFail($payload['units'], $unit);
            if (collect($payload['sales'])->contains(fn (array $sale): bool => $sale['unit_id'] === strtolower($unit))) {
                throw ValidationException::withMessages(['unit' => 'Exclua ou transfira as vendas desta unidade primeiro.']);
            }
            $payload['units'] = array_values(array_filter($payload['units'], fn (array $item): bool => $item['id'] !== strtolower($unit)));

            return $payload;
        });
    }

    public function deleteSale(User $user, int $company, string $sale): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($sale): array {
            $this->indexOrFail($payload['sales'], $sale);
            $payload['sales'] = array_values(array_filter($payload['sales'], fn (array $item): bool => $item['id'] !== strtolower($sale)));

            return $payload;
        });
    }

    public function deletePayment(User $user, int $company, string $sale, string $payment): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($sale, $payment): array {
            $saleIndex = $this->indexOrFail($payload['sales'], $sale);
            $this->indexOrFail($payload['sales'][$saleIndex]['payments'], $payment);
            $payload['sales'][$saleIndex]['payments'] = array_values(array_filter(
                $payload['sales'][$saleIndex]['payments'],
                fn (array $item): bool => $item['id'] !== strtolower($payment),
            ));

            return $payload;
        });
    }

    public function deletePlate(User $user, int $company, string $sale, string $plate): void
    {
        $this->changeRecord($user, $company, NfcRecord::COMPANY, function (array $payload) use ($sale, $plate): array {
            $saleIndex = $this->indexOrFail($payload['sales'], $sale);
            $this->indexOrFail($payload['sales'][$saleIndex]['plates'], $plate);
            $payload['sales'][$saleIndex]['plates'] = array_values(array_filter(
                $payload['sales'][$saleIndex]['plates'],
                fn (array $item): bool => $item['id'] !== strtolower($plate),
            ));

            return $payload;
        });
    }

    private function createRecord(User $user, string $kind, string $key, array $payload): NfcRecord
    {
        return DB::transaction(function () use ($user, $kind, $key, $payload): NfcRecord {
            $record = NfcRecord::query()->firstOrCreate([
                'user_id' => $user->id,
                'kind' => $kind,
                'record_key' => strtolower($key),
            ], ['payload' => $payload]);

            if ($record->wasRecentlyCreated) {
                $this->recordHistory($user, 'created', $record, [], $payload);
            }

            return $record;
        }, 3);
    }

    private function changeRecord(User $user, int $id, string|array $kinds, Closure $change, string $event = 'updated'): void
    {
        DB::transaction(function () use ($user, $id, $kinds, $change, $event): void {
            $record = NfcRecord::query()->whereBelongsTo($user)->whereIn('kind', (array) $kinds)
                ->lockForUpdate()->findOrFail($id);
            $before = $record->payload;
            abort_if(! empty($before['deleted_at']), 404);
            $after = $change($before);

            if ($after !== $before) {
                $record->update(['payload' => $after]);
                $this->recordHistory($user, $event, $record, $before, $after);
            }
        }, 3);
    }

    private function recordHistory(User $user, string $event, NfcRecord $record, array $before, array $after): void
    {
        NfcRecord::query()->create([
            'user_id' => $user->id,
            'kind' => NfcRecord::HISTORY,
            'record_key' => (string) Str::uuid(),
            'payload' => [
                'event' => $event,
                'target_kind' => $record->kind,
                'target_id' => $record->id,
                'label' => $after['name'] ?? $before['name'] ?? ($after['text'] ?? $before['text'] ?? null),
                'actor' => request()->attributes->has('nfc_share_link')
                    ? 'Link compartilhado #'.request()->attributes->get('nfc_share_link')->id
                    : $user->name,
                'occurred_at' => now()->toISOString(),
                'before' => $before,
                'after' => $after,
            ],
        ]);
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
            'status' => $data['status'] ?? 'completed',
            'quantity' => $quantity,
            'customized_quantity' => $customizedQuantity,
            'cost_cents' => isset($data['cost_cents']) ? (int) $data['cost_cents'] : null,
        ];
    }

    private function validatedManufacturerId(User $user, ?int $manufacturerId): ?int
    {
        if ($manufacturerId === null) {
            return null;
        }

        $record = NfcRecord::query()->whereBelongsTo($user)
            ->where('kind', NfcRecord::MANUFACTURER)->find($manufacturerId);

        if (! $record || ! empty($record->payload['deleted_at'])) {
            throw ValidationException::withMessages(['manufacturer_id' => 'Selecione um fabricante válido.']);
        }

        return $manufacturerId;
    }

    private function validatedProductionId(User $user, ?int $productionId): ?int
    {
        if ($productionId === null) {
            return null;
        }

        $exists = NfcRecord::query()->whereBelongsTo($user)
            ->whereIn('kind', [NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION])
            ->whereKey($productionId)->first();

        if (! $exists || ! empty($exists->payload['deleted_at'])) {
            throw ValidationException::withMessages(['production_id' => 'Selecione um lote de fabricação válido.']);
        }

        return $productionId;
    }

    private function newPlate(string $type, string $label, bool $customizing = false): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'label' => $label,
            'notes' => null,
            'customizing' => $customizing,
            'defective' => false,
            'estimated_loss_cents' => null,
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
