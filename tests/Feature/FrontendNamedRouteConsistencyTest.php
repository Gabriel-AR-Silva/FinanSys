<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class FrontendNamedRouteConsistencyTest extends TestCase
{
    public function test_literal_named_routes_used_by_active_frontend_are_registered(): void
    {
        $ignoredFiles = [
            resource_path('js/Pages/Auth/Register.vue'),
            resource_path('js/Pages/Profile/Partials/DeleteUserForm.vue'),
        ];

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'))),
            '/\.vue$/i',
        );

        $missing = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (in_array($path, $ignoredFiles, true)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            preg_match_all("/route\(\s*['\"]([^'\"]+)['\"]/", $content, $matches);

            foreach (array_unique($matches[1] ?? []) as $routeName) {
                if (! Route::has($routeName)) {
                    $missing[$routeName][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                }
            }
        }

        self::assertSame([], $missing, 'Frontend references missing named routes: '.json_encode($missing, JSON_PRETTY_PRINT));
    }
}
