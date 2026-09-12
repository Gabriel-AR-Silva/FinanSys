<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\ReceiptForecastStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class CreateReceiptForecast
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{category_id:int,amount:string,expected_on:string,operation_id:string,recurrence_count?:int} $data */
    public function handle(User $user, array $data): ReceiptForecast
    {
        $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
        $operationId = strtolower($data['operation_id']);
        $recurrenceCount = (int) ($data['recurrence_count'] ?? 1);
        $firstDate = CarbonImmutable::createFromFormat('!Y-m-d', $data['expected_on'], 'America/Sao_Paulo');
        $originalDay = $firstDate->day;

        return DB::transaction(function () use ($user, $data, $amount, $operationId, $recurrenceCount, $firstDate, $originalDay): ReceiptForecast {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = ReceiptForecast::query()->whereBelongsTo($user)->where('operation_id', $operationId)->first();
            if ($existing) {
                $series = $existing->series_id === null
                    ? collect([$existing])
                    : ReceiptForecast::query()->whereBelongsTo($user)->where('series_id', $existing->series_id)->orderBy('series_position')->get();
                if ($series->count() !== $recurrenceCount || $series->contains(function (ReceiptForecast $forecast, int $index) use ($data, $amount, $firstDate, $originalDay): bool {
                    $expectedDate = $this->occurrenceDate($firstDate, $originalDay, $index);

                    return (int) $forecast->category_id !== (int) $data['category_id'] || $forecast->amount !== $amount
                        || $forecast->expected_on->toDateString() !== $expectedDate;
                })) {
                    throw ValidationException::withMessages(['operation_id' => 'Esta operação já foi usada com outros dados. Confira a listagem antes de iniciar uma nova previsão.']);
                }

                return $existing;
            }

            $category = Category::query()->whereBelongsTo($user)->whereKey($data['category_id'])->lockForUpdate()->first();
            if (! $category || $category->type !== CategoryType::Income || $category->status !== RecordStatus::Active) {
                throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de receita disponível.']);
            }

            $seriesId = $recurrenceCount > 1 ? $operationId : null;
            $forecast = null;
            for ($position = 0; $position < $recurrenceCount; $position++) {
                $occurrence = ReceiptForecast::query()->create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'expected_on' => $this->occurrenceDate($firstDate, $originalDay, $position),
                    'status' => ReceiptForecastStatus::Expected,
                    'operation_id' => $position === 0 ? $operationId : Uuid::uuid5($operationId, 'receipt-forecast:'.$position)->toString(),
                    'series_id' => $seriesId,
                    'series_position' => $seriesId === null ? null : $position,
                    'original_day' => $seriesId === null ? null : $originalDay,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $occurrence);
                $forecast ??= $occurrence;
            }

            $this->refreshAlert->handle($user);

            return $forecast;
        }, 3);
    }

    private function occurrenceDate(CarbonImmutable $firstDate, int $originalDay, int $position): string
    {
        $month = $firstDate->startOfMonth()->addMonths($position);
        if ($month->year > 9999) {
            throw ValidationException::withMessages(['expected_on' => 'A repetição ultrapassa o limite do calendário suportado.']);
        }

        return $month->day(min($originalDay, $month->daysInMonth))->toDateString();
    }
}
