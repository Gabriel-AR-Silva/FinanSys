<?php

namespace App\Http\Controllers;

use App\Queries\FinancialDiagnosticExportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FinancialDiagnosticExportController extends Controller
{
    public function __invoke(Request $request, FinancialDiagnosticExportQuery $export): JsonResponse
    {
        $date = now('America/Sao_Paulo')->format('Y-m-d_H-i-s');

        return response()->json(
            $export->forUser($request->user()),
            200,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'attachment; filename="finansys-diagnostic-'.$date.'.json"',
                'X-Content-Type-Options' => 'nosniff',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
