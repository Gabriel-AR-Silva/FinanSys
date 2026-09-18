<?php

namespace App\Http\Controllers;

use App\Actions\LinkReceiptForecast;
use App\Actions\RecordForecastReceipt;
use App\Http\Requests\StoreReceiptForecastReceiptRequest;
use Illuminate\Http\RedirectResponse;

class ReceiptForecastReceiptController extends Controller
{
    public function store(StoreReceiptForecastReceiptRequest $request, int $forecast, LinkReceiptForecast $linkReceipt, RecordForecastReceipt $recordReceipt): RedirectResponse
    {
        $data = $request->validated();
        $newReceipt = ($data['mode'] ?? 'existing') === 'new';
        $link = $newReceipt
            ? $recordReceipt->handle($request->user(), $forecast, $data)
            : $linkReceipt->handle(
                $request->user(),
                $forecast,
                (int) $data['ledger_entry_id'],
                (int) $data['forecast_version'],
                $data['operation_id'],
            );

        $params = ['month' => $link->forecast->expected_on->format('Y-m')];
        if ($request->boolean('from_settings')) {
            $params['tab'] = 'receipts';
        }

        return to_route($request->boolean('from_settings') ? 'financial-settings.edit' : 'receipt-forecasts.index', $params)
            ->with('success', $newReceipt
                ? '✅ Recebimento registrado na conta e vinculado à previsão, sem duplicidade.'
                : '✅ Recebimento vinculado. O que entrou e o que ainda falta foram recalculados.');
    }
}
