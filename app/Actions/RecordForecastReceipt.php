<?php

namespace App\Actions;

use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class RecordForecastReceipt
{
    public function __construct(
        private CreateManualLedgerEntry $createEntry,
        private LinkReceiptForecast $linkReceipt,
    ) {}

    /** @param array{account_id:int,amount:string,occurred_at:string,forecast_version:int,operation_id:string} $data */
    public function handle(User $user, int $forecastId, array $data): ReceiptForecastLink
    {
        $entryOperationId = Uuid::uuid5(Uuid::NAMESPACE_URL, 'finansys:forecast-receipt:entry:'.strtolower($data['operation_id']))->toString();
        $linkOperationId = Uuid::uuid5(Uuid::NAMESPACE_URL, 'finansys:forecast-receipt:link:'.strtolower($data['operation_id']))->toString();

        return DB::transaction(function () use ($user, $forecastId, $data, $entryOperationId, $linkOperationId): ReceiptForecastLink {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $forecast = ReceiptForecast::query()->whereBelongsTo($user)->whereKey($forecastId)->lockForUpdate()->firstOrFail();
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)
                ->whereKey((int) $data['account_id'])->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['account_id' => 'Escolha uma conta sua que esteja ativa.']);
            }

            $entry = $this->createEntry->handle(
                user: $user,
                accountId: $account->getKey(),
                categoryId: (int) $forecast->category_id,
                type: LedgerEntryType::Income,
                value: $data['amount'],
                occurredAt: $data['occurred_at'],
                description: 'Recebimento previsto #'.$forecast->getKey(),
                operationId: $entryOperationId,
            );

            return $this->linkReceipt->handle(
                $user,
                $forecastId,
                $entry->getKey(),
                (int) $data['forecast_version'],
                $linkOperationId,
            );
        }, 3);
    }
}
