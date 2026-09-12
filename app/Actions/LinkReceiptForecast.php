<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastStatus;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\ReceiptForecastLinkOperation;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkReceiptForecast
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RecalculateReceiptForecast $recalculate,
    ) {}

    public function handle(User $user, int $forecastId, int $ledgerEntryId, int $forecastVersion, string $operationId): ReceiptForecastLink
    {
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        try {
            return DB::transaction(function () use ($user, $forecastId, $ledgerEntryId, $forecastVersion, $operationId): ReceiptForecastLink {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $operationId = strtolower($operationId);
                $replay = ReceiptForecastLinkOperation::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('link')->first();
                if ($replay) {
                    return $this->validateReplay($replay, $forecastId, $ledgerEntryId);
                }

                $forecast = ReceiptForecast::query()->whereBelongsTo($user)->whereKey($forecastId)->lockForUpdate()->firstOrFail();
                if ($forecast->version !== $forecastVersion) {
                    throw ValidationException::withMessages(['forecast_version' => 'Esta previsão mudou em outra aba. Recarregue antes de vincular o recebimento.']);
                }
                if ($forecast->status !== ReceiptForecastStatus::Expected) {
                    throw ValidationException::withMessages(['forecast' => 'Somente uma previsão pendente pode receber novos vínculos.']);
                }

                $entry = LedgerEntry::query()->whereBelongsTo($user)->whereKey($ledgerEntryId)->lockForUpdate()->firstOrFail();
                $wasReversed = LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $entry->operation_id)->exists();
                if ($entry->type !== LedgerEntryType::Income || $entry->reversal_of_operation_id !== null || $wasReversed) {
                    throw ValidationException::withMessages(['ledger_entry_id' => 'Escolha uma receita disponível para vincular.']);
                }
                $previous = ReceiptForecastLink::query()->whereBelongsTo($user)->whereBelongsTo($entry, 'ledgerEntry')->lockForUpdate()->first();
                if ($previous !== null && $previous->unlinked_at === null) {
                    throw ValidationException::withMessages(['ledger_entry_id' => 'Esta receita já foi vinculada a uma previsão.']);
                }

                if ($previous) {
                    $link = $previous;
                    $before = $link->attributesToArray();
                    $link->update([
                        'receipt_forecast_id' => $forecast->id,
                        'linked_at' => now(),
                        'unlinked_at' => null,
                        'unlink_reason' => null,
                        'version' => $link->version + 1,
                    ]);
                    $kind = 'relinked';
                } else {
                    $link = ReceiptForecastLink::query()->create([
                        'user_id' => $user->id,
                        'receipt_forecast_id' => $forecast->id,
                        'ledger_entry_id' => $entry->id,
                        'operation_id' => $operationId,
                        'linked_at' => now(),
                    ]);
                    $before = null;
                    $kind = 'linked';
                }
                ReceiptForecastLinkOperation::query()->create([
                    'user_id' => $user->id,
                    'receipt_forecast_link_id' => $link->id,
                    'receipt_forecast_id' => $forecast->id,
                    'ledger_entry_id' => $entry->id,
                    'operation_id' => $operationId,
                    'kind' => $kind,
                ]);
                $this->auditRecorder->record($user, AuditAction::Linked, $link, $before);
                $this->recalculate->handle($user, $forecast);

                return $link;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $replay = ReceiptForecastLinkOperation::query()->whereBelongsTo($user)->where('operation_id', strtolower($operationId))->with('link')->first();
            if ($replay) {
                return $this->validateReplay($replay, $forecastId, $ledgerEntryId);
            }

            throw ValidationException::withMessages(['ledger_entry_id' => 'Esta receita já foi vinculada a uma previsão.']);
        }
    }

    private function validateReplay(ReceiptForecastLinkOperation $operation, int $forecastId, int $ledgerEntryId): ReceiptForecastLink
    {
        if ($operation->receipt_forecast_id !== $forecastId || $operation->ledger_entry_id !== $ledgerEntryId || ! $operation->link) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);
        }

        return $operation->link;
    }
}
