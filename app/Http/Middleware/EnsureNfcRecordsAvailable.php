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
        if (Schema::hasTable('nfc_records') && Schema::hasTable('nfc_access_links')) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            return $request->attributes->has('nfc_share_owner')
                ? to_route('nfc.share.index', ['token' => $request->attributes->get('nfc_share_token')], status: 303)
                : to_route('nfc.index', status: 303);
        }

        return Inertia::render('Nfc/Index', [
            'schema_pending' => true,
            'shared' => $request->attributes->has('nfc_share_owner'),
            'share_token' => $request->attributes->has('nfc_share_owner') ? $request->attributes->get('nfc_share_token') : null,
        ])->toResponse($request);
    }
}
