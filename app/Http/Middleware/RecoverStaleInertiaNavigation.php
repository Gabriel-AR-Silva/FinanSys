<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecoverStaleInertiaNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isStaleDocumentNavigation($request)) {
            $request->headers->remove('X-Inertia');
            $request->headers->remove('X-Inertia-Version');
            $request->headers->remove('X-Requested-With');
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function isStaleDocumentNavigation(Request $request): bool
    {
        if (! $request->isMethod('GET') || ! $request->header('X-Inertia')) {
            return false;
        }

        return strtolower((string) $request->header('Sec-Fetch-Mode')) === 'navigate'
            || strtolower((string) $request->header('Sec-Fetch-Dest')) === 'document';
    }
}
