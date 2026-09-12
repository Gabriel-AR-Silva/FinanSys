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

class UpdateReceiptForecast
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RecalculateReceiptForecast $recalculate,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{category_id:int,amount:string,expected_on:string,version:int,edit_scope?:string} $data */
    public function handle(User $user, int $forecastId, array $data): ReceiptForecast
    {
        return DB::transaction(function () use ($user, $forecastId, $data): ReceiptForecast {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $forecast = ReceiptForecast::query()->whereBelongsTo($user)->whereKey($forecastId)->withCount('activeLinks')->lockForUpdate()->firstOrFail();
            if ($forecast->status !== ReceiptForecastStatus::Expected) {
                throw ValidationException::withMessages(['forecast' => 'Uma previsão concluída ou cancelada não pode ser editada.']);
            }
            if ($forecast->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Esta previsão mudou em outra aba. Recarregue antes de editar.']);
            }
            $scope = $data['edit_scope'] ?? 'this';
            $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
            if ($forecast->active_links_count > 0 && $scope !== 'this') {
                throw ValidationException::withMessages(['edit_scope' => 'Uma ocorrência parcialmente recebida só pode remarcar o próprio residual.']);
            }
            if ($forecast->active_links_count > 0 && BigDecimal::of($amount)->isLessThan($this->recalculate->calculate($user, $forecast)['received'])) {
                throw ValidationException::withMessages(['amount' => 'O total previsto não pode ficar abaixo do valor que já foi recebido.']);
            }

            $category = Category::query()->whereBelongsTo($user)->whereKey($data['category_id'])->lockForUpdate()->first();
            if (! $category || $category->type !== CategoryType::Income
                || ($category->status !== RecordStatus::Active && $forecast->category_id !== $category->id)) {
                throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de receita disponível.']);
            }

            if ($scope === 'future' && $forecast->series_id === null) {
                throw ValidationException::withMessages(['edit_scope' => 'Esta previsão não pertence a uma série mensal.']);
            }

            $targets = $scope === 'future'
                ? ReceiptForecast::query()->whereBelongsTo($user)->where('series_id', $forecast->series_id)
                    ->where('series_position', '>=', $forecast->series_position)->where('status', ReceiptForecastStatus::Expected)
                    ->whereDoesntHave('activeLinks')->orderBy('series_position')->lockForUpdate()->get()
                : collect([$forecast]);
            $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $data['expected_on'], 'America/Sao_Paulo');
            $originalDay = $selectedDate->day;
            $changed = false;

            $targets->each(function (ReceiptForecast $target) use ($user, $forecast, $category, $amount, $selectedDate, $originalDay, $scope, &$changed): void {
                $before = $target->attributesToArray();
                $expectedOn = $scope === 'future'
                    ? $this->occurrenceDate($selectedDate, $originalDay, $target->series_position - $forecast->series_position)
                    : $selectedDate->toDateString();
                $target->fill([
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'expected_on' => $expectedOn,
                    'original_day' => $scope === 'future' ? $originalDay : $target->original_day,
                ]);
                if ($target->isDirty()) {
                    $target->version++;
                    $target->save();
                    $this->auditRecorder->record($user, AuditAction::Updated, $target, $before);
                    $changed = true;
                }
            });

            if ($changed) {
                $this->refreshAlert->handle($user);
            }

            return $targets->firstWhere('id', $forecast->id) ?? $forecast;
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
