<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('workspacePages')]
    public function test_authenticated_user_can_render_workspace_pages(string $routeName, string $component): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route($routeName));

        $response->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('workspacePages')]
    public function test_unauthenticated_user_is_redirected_to_login(string $routeName, string $_component): void
    {
        $response = $this->get(route($routeName));

        $response->assertRedirect(route('login'));
    }

    public static function workspacePages(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard'],
            'accounts' => ['accounts.index', 'Accounts/Index'],
            'pockets' => ['pockets.index', 'Pockets/Index'],
            'ledger entries' => ['ledger-entries.index', 'LedgerEntries/Index'],
        ];
    }
}
