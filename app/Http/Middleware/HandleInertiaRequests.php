<?php

namespace App\Http\Middleware;

use App\Queries\OnboardingProgressQuery;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->header('X-Inertia') && $this->isDocumentNavigation($request)) {
            $request->headers->remove('X-Inertia');
            $request->headers->remove('X-Inertia-Version');
            $request->headers->remove('X-Requested-With');
        }

        $response = parent::handle($request, $next);

        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Vary', 'X-Inertia, X-Inertia-Version, Accept', false);

        return $response;
    }

    private function isDocumentNavigation(Request $request): bool
    {
        return strtolower((string) $request->header('Sec-Fetch-Mode')) === 'navigate'
            || strtolower((string) $request->header('Sec-Fetch-Dest')) === 'document';
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'features' => [
                'ofx' => (bool) config('features.ofx', false),
            ],
            'onboarding' => fn (): ?array => $request->user()
                ? app(OnboardingProgressQuery::class)->forUser($request->user())
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
