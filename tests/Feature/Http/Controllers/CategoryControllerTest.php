<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_categories_with_usage_count(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Moradia', 'type' => 'expense']);
        LedgerEntry::factory()->count(2)->for($user)->for($category)->expense()->create();
        Category::factory()->for(User::factory())->create(['name' => 'Alheia']);

        $this->actingAs($user)->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Categories/Index')->has('categories', 1)
                ->where('categories.0.name', 'Moradia')->where('categories.0.entries_count', 2));
    }

    public function test_user_can_create_a_trimmed_audited_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('categories.store'), ['name' => '  Freelance  ', 'type' => 'income'])
            ->assertRedirect(route('categories.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'name' => 'Freelance', 'type' => 'income', 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'created', 'auditable_type' => 'category']);
    }

    public function test_duplicate_name_for_same_type_is_rejected_without_case_or_whitespace_difference(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Salário', 'type' => 'income', 'status' => RecordStatus::Inactive]);

        $this->actingAs($user)->post(route('categories.store'), ['name' => ' salário ', 'type' => 'income'])
            ->assertSessionHasErrors(['name' => 'Já existe uma categoria com este nome para este tipo.']);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_same_name_is_allowed_for_the_other_type(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Outros', 'type' => 'income']);

        $this->actingAs($user)->post(route('categories.store'), ['name' => 'Outros', 'type' => 'expense'])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseCount('categories', 2);
    }

    public function test_user_can_rename_a_used_category_and_the_change_is_audited(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Casa', 'type' => 'expense']);
        $entry = LedgerEntry::factory()->for($user)->for($category)->expense()->create();

        $this->actingAs($user)->put(route('categories.update', $category), ['name' => 'Moradia'])
            ->assertRedirect(route('categories.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Moradia']);
        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'category_id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'updated', 'auditable_id' => $category->id]);
    }

    public function test_user_can_deactivate_and_reactivate_a_used_category_without_losing_history(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->for($category)->create(['type' => $category->type->value]);

        $this->actingAs($user)->patch(route('categories.status.update', $category), ['status' => 'inactive'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'category_id' => $category->id]);

        $this->actingAs($user)->patch(route('categories.status.update', $category), ['status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_user_cannot_rename_or_change_another_users_category(): void
    {
        $category = Category::factory()->for(User::factory())->create(['name' => 'Privada']);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->put(route('categories.update', $category), ['name' => 'Invadida'])->assertNotFound();
        $this->actingAs($attacker)->patch(route('categories.status.update', $category), ['status' => 'inactive'])->assertNotFound();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Privada', 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_payloads_are_rejected_with_visible_messages(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)->post(route('categories.store'), ['name' => '', 'type' => 'transfer'])
            ->assertSessionHasErrors(['name' => 'Informe o nome da categoria.', 'type' => 'Escolha receita ou despesa.']);
        $this->actingAs($user)->patch(route('categories.status.update', $category), ['status' => 'deleted'])
            ->assertSessionHasErrors(['status' => 'Escolha um status válido para a categoria.']);
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $this->get(route('categories.index'))->assertRedirect(route('login'));
        $this->post(route('categories.store'))->assertRedirect(route('login'));
        $this->put(route('categories.update', 1))->assertRedirect(route('login'));
        $this->patch(route('categories.status.update', 1))->assertRedirect(route('login'));

    }
}
