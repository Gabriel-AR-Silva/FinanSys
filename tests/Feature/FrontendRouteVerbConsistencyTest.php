<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class FrontendRouteVerbConsistencyTest extends TestCase
{
    public function test_explicit_frontend_mutations_use_an_allowed_http_verb(): void
    {
        $ignoredFiles = [
            resource_path('js/Pages/Auth/Register.vue'),
            resource_path('js/Pages/Profile/Partials/DeleteUserForm.vue'),
        ];

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'))),
            '/\.vue$/i',
        );

        $mismatches = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (in_array($path, $ignoredFiles, true)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            foreach (['post' => 'POST', 'put' => 'PUT', 'patch' => 'PATCH', 'delete' => 'DELETE'] as $method => $verb) {
                preg_match_all(
                    "/(?:\brouter|\bwindow\.axios|\baxios|[A-Za-z_$][A-Za-z0-9_$]*)\.{$method}\(\s*route\(\s*['\"]([^'\"]+)['\"]/",
                    $content,
                    $matches,
                );

                foreach (array_unique($matches[1] ?? []) as $routeName) {
                    if (! Route::has($routeName)) {
                        continue;
                    }

                    $route = Route::getRoutes()->getByName($routeName);
                    $allowed = $route?->methods() ?? [];

                    if (! in_array($verb, $allowed, true)) {
                        $mismatches[] = [
                            'file' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                            'route' => $routeName,
                            'used' => $verb,
                            'allowed' => $allowed,
                        ];
                    }
                }
            }
        }

        self::assertSame([], $mismatches, 'Frontend mutation verb mismatches: '.json_encode($mismatches, JSON_PRETTY_PRINT));
    }
}
