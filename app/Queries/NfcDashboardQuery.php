<?php

namespace App\Queries;

use App\Models\NfcRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class NfcDashboardQuery
{
    public function forUser(User $user): array
    {
        $today = CarbonImmutable::today('America/Sao_Paulo');
        $records = NfcRecord::query()->whereBelongsTo($user)->latest('id')->get();
        $companies = $records->where('kind', NfcRecord::COMPANY)
            ->map(fn (NfcRecord $record): array => $this->company($record, $today))
            ->values();
        $production = $records->whereIn('kind', [NfcRecord::PRODUCTION, NfcRecord::INITIAL_PRODUCTION])
            ->map(fn (NfcRecord $record): array => [
                'id' => $record->id,
                'initial' => $record->kind === NfcRecord::INITIAL_PRODUCTION,
                ...$record->payload,
                'status' => $record->payload['status'] ?? 'completed',
            ])->values();
        $companies = $companies->map(function (array $company) use ($production): array {
            return [
                ...$company,
                'units' => array_map(function (array $unit) use ($production): array {
                    $batch = $production->firstWhere('id', $unit['production_id'] ?? null);
                    return [
                        ...$unit,
                        'production_status' => $batch['status'] ?? null,
                        'batch_quantity' => $batch['quantity'] ?? null,
                    ];
                }, $company['units']),
            ];
        });

        $sales = $companies->flatMap(fn (array $company): array => $company['sales']);
        $plates = $sales->flatMap(fn (array $sale): array => $sale['plates']);

        return [
            'today' => $today->toDateString(),
            'summary' => [
                'interested' => $companies->where('interested', true)->count(),
                'in_customization' => $plates->where('customizing', true)->count(),
                'manufactured' => $production->where('status', 'completed')->sum('quantity'),
                'in_production' => $production->where('status', 'in_progress')->sum('quantity'),
                'sold' => $plates->count(),
                'sold_standard' => $plates->where('type', 'standard')->count(),
                'sold_custom' => $plates->where('type', 'custom')->count(),
                'defective' => $plates->where('defective', true)->count(),
                'estimated_loss_cents' => $plates->where('defective', true)->sum('estimated_loss_cents'),
                'unestimated_defects' => $plates->where('defective', true)->whereNull('estimated_loss_cents')->count(),
                'paid_plates' => $sales->where('payment_status', 'paid')->sum(fn (array $sale): int => count($sale['plates'])),
                'production_cost_cents' => $production->sum('cost_cents'),
                'unknown_cost_batches' => $production->whereNull('cost_cents')->count(),
                'received_cents' => $sales->sum('paid_cents'),
                'outstanding_cents' => $sales->sum('balance_cents'),
            ],
            'companies' => $companies->all(),
            'production' => $production->all(),
            'notices' => $this->notices($records->where('kind', NfcRecord::NOTICE), $companies->keyBy('id')),
            'warranty_notices' => $this->warrantyNotices($companies, $today),
        ];
    }

    private function company(NfcRecord $record, CarbonImmutable $today): array
    {
        $company = $record->payload;
        $sales = collect($company['sales'])->map(function (array $sale) use ($today): array {
            $paid = array_sum(array_column($sale['payments'], 'amount_cents'));
            $balance = $sale['total_cents'] - $paid;
            $warrantyUntil = CarbonImmutable::parse($sale['sold_on'], $today->timezone)->addMonthsNoOverflow(3);

            return [
                ...$sale,
                'paid_cents' => $paid,
                'balance_cents' => $balance,
                'payment_status' => $balance === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
                'warranty_until' => $warrantyUntil->toDateString(),
                'warranty_status' => $warrantyUntil->lt($today) ? 'expired' : 'active',
            ];
        });

        return [
            ...$company,
            'id' => $record->id,
            'sales' => $sales->all(),
            'sold' => $sales->sum(fn (array $sale): int => count($sale['plates'])),
            'outstanding_cents' => $sales->sum('balance_cents'),
        ];
    }

    private function notices(Collection $records, Collection $companies): array
    {
        return $records->map(function (NfcRecord $record) use ($companies): array {
            $notice = $record->payload;
            $company = $notice['company_id'] === null ? null : $companies->get($notice['company_id']);
            $sale = $notice['plate_id'] ? collect($company['sales'] ?? [])->first(
                fn (array $sale): bool => in_array($notice['plate_id'], array_column($sale['plates'], 'id'), true),
            ) : null;
            $unit = collect($company['units'] ?? [])->firstWhere('id', $sale['unit_id'] ?? null);
            $plate = collect($sale['plates'] ?? [])->firstWhere('id', $notice['plate_id']);

            return [
                ...$notice,
                'id' => $record->id,
                'company_name' => $company['name'] ?? null,
                'unit_name' => $unit['name'] ?? null,
                'plate_label' => $plate['label'] ?? null,
            ];
        })->values()->all();
    }

    private function warrantyNotices(Collection $companies, CarbonImmutable $today): array
    {
        $windowEnd = $today->addDays(7)->toDateString();

        return $companies->flatMap(function (array $company) use ($windowEnd): Collection {
            $units = collect($company['units'])->keyBy('id');

            return collect($company['sales'])
                ->where('warranty_status', 'active')
                ->where('warranty_until', '<=', $windowEnd)
                ->map(fn (array $sale): array => [
                    'company_id' => $company['id'],
                    'company_name' => $company['name'],
                    'unit_name' => $units->get($sale['unit_id'])['name'] ?? null,
                    'sale_id' => $sale['id'],
                    'warranty_until' => $sale['warranty_until'],
                    'plate_count' => count($sale['plates']),
                ]);
        })->sortBy('warranty_until')->values()->all();
    }
}
