<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureNfcRecordsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('nfc_records')) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            return to_route('nfc.index', status: 303);
        }

        return Inertia::render('Nfc/Index', ['schema_pending' => true])->toResponse($request);
    }
}
