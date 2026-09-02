<?php

namespace App\Http\Controllers;

use App\Actions\ChangeCategoryStatus;
use App\Actions\CreateCategory;
use App\Actions\RenameCategory;
use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateCategoryStatusRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()->whereBelongsTo($request->user())
            ->withCount('ledgerEntries')
            ->orderBy('type')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'status' => $category->status->value,
                'entries_count' => $category->ledger_entries_count,
            ]);

        return Inertia::render('Categories/Index', ['categories' => $categories]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $data = $request->validated();
        $createCategory->handle($request->user(), $data['name'], CategoryType::from($data['type']));

        return to_route('categories.index')->with('success', 'Categoria criada com sucesso.');
    }

    public function update(UpdateCategoryRequest $request, int $category, RenameCategory $renameCategory): RedirectResponse
    {
        $renameCategory->handle($request->user(), $category, $request->validated('name'));

        return to_route('categories.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    public function updateStatus(UpdateCategoryStatusRequest $request, int $category, ChangeCategoryStatus $changeStatus): RedirectResponse
    {
        $changeStatus->handle($request->user(), $category, RecordStatus::from($request->validated('status')));

        return to_route('categories.index')->with('success', 'Status da categoria atualizado com sucesso.');
    }
}
