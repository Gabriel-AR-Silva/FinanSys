<?php

namespace App\Http\Controllers;

use App\Queries\FinancialEvaluationHistoryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialEvaluationController extends Controller
{
    public function index(Request $request, FinancialEvaluationHistoryQuery $history): Response
    {
        $data = $request->validate([
            'view' => ['sometimes', 'string', 'in:current,projected'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $to = isset($data['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], 'America/Sao_Paulo') : $today;
        $from = isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], 'America/Sao_Paulo') : $to->subDays(29);
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'O início não pode ser posterior ao fim.']);
        }

        return Inertia::render('FinancialEvaluations/Index', [
            'evaluations' => $history->paginate($request->user(), $data['view'] ?? 'current', $from, $to),
            'filters' => ['view' => $data['view'] ?? 'current', 'from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
