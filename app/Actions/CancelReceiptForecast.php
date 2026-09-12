<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\ReceiptForecastStatus;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReceiptForecast
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $forecastId, int $version): ReceiptForecast
    {
        return DB::transaction(function () use ($user, $forecastId, $version): ReceiptForecast {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $forecast = ReceiptForecast::query()->whereBelongsTo($user)->whereKey($forecastId)->lockForUpdate()->firstOrFail();
            if ($forecast->status === ReceiptForecastStatus::Cancelled) {
                return $forecast;
            }
            if ($forecast->version !== $version) {
                throw ValidationException::withMessages(['version' => 'Esta previsão mudou em outra aba. Recarregue antes de cancelar.']);
            }

            $before = $forecast->attributesToArray();
            $forecast->update(['status' => ReceiptForecastStatus::Cancelled, 'version' => $forecast->version + 1]);
            $this->auditRecorder->record($user, AuditAction::Updated, $forecast, $before);

            return $forecast;
        }, 3);
    }
}
