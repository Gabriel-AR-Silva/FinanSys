<?php

namespace App\Http\Controllers;

use App\Actions\LinkReceiptForecast;
use App\Http\Requests\StoreReceiptForecastReceiptRequest;
use Illuminate\Http\RedirectResponse;

class ReceiptForecastReceiptController extends Controller
{
    public function store(StoreReceiptForecastReceiptRequest $request, int $forecast, LinkReceiptForecast $linkReceipt): RedirectResponse
    {
        $data = $request->validated();
        $link = $linkReceipt->handle(
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
            ->with('success', '✅ Recebimento vinculado. O que entrou e o que ainda falta foram recalculados.');
    }
}
