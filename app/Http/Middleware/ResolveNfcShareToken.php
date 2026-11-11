<?php

namespace App\Http\Middleware;

use App\Models\NfcAccessLink;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveNfcShareToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Schema::hasTable('nfc_access_links'), 404);
        $token = (string) $request->route('token');
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token) === 1, 404);
        abort_unless(Schema::hasTable('nfc_access_links'), 404);

        $link = NfcAccessLink::query()->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_if($link->user === null, 404);
        $request->attributes->set('nfc_share_owner', $link->user);
        $request->attributes->set('nfc_share_link', $link);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
