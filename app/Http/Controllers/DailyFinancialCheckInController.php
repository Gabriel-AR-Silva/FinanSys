<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmDailyFinancialCheckInBatch;
use App\Actions\RecordDailyFinancialCheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyFinancialCheckInController extends Controller
{
    public function store(Request $request, RecordDailyFinancialCheckIn $recorder): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'operation_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $recorder->confirm(
            $request->user(),
            $data['date'],
            $data['operation_id'],
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Dia confirmado.');
    }

    public function storeBatch(Request $request, ConfirmDailyFinancialCheckInBatch $confirm): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'min:1', 'max:31'],
            'days.*.date' => ['required', 'date_format:Y-m-d'],
            'days.*.operation_id' => ['required', 'uuid', 'distinct'],
            'days.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $confirm->handle($request->user(), $data['days']);

        return back()->with('success', count($data['days']).' dia(s) confirmado(s).');
    }

    public function correct(Request $request, RecordDailyFinancialCheckIn $recorder): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'operation_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $recorder->correct(
            $request->user(),
            $data['date'],
            $data['operation_id'],
            $data['reason'],
        );

        return back()->with('success', 'Correção do dia registrada.');
    }
}
