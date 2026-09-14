<?php

namespace App\Http\Controllers;

use App\Queries\InternalAlertQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InternalAlertController extends Controller
{
    public function index(Request $request, InternalAlertQuery $alerts): Response
    {
        $data = $request->validate([
            'view' => ['sometimes', 'string', 'in:current,projected'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', 'in:active,recovered'],
            'deficit_only' => ['sometimes', 'boolean'],
        ]);
        $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $to = isset($data['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], 'America/Sao_Paulo') : $today;
        $from = isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], 'America/Sao_Paulo') : $to->subDays(29);
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'O início não pode ser posterior ao fim.']);
        }

        $schemaReady = Schema::hasTable('internal_alerts');

        return Inertia::render('InternalAlerts/Index', [
            'alerts' => $schemaReady
                ? $alerts->paginate($request->user(), $data['view'] ?? 'current', $from, $to, $data['status'] ?? null, (bool) ($data['deficit_only'] ?? false))
                : $this->emptyPagination(),
            'schemaWarning' => $schemaReady
                ? null
                : 'Os avisos financeiros ainda não foram sincronizados neste ambiente. Execute as migrations pendentes antes de validar este módulo.',
            'filters' => [
                'view' => $data['view'] ?? 'current',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'status' => $data['status'] ?? 'all',
                'deficit_only' => (bool) ($data['deficit_only'] ?? false),
            ],
        ]);
    }

    /** @return array{data:array<never>,current_page:int,last_page:int,per_page:int,total:int,prev_page_url:null,next_page_url:null} */
    private function emptyPagination(): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 20,
            'total' => 0,
            'prev_page_url' => null,
            'next_page_url' => null,
        ];
    }
}
