<?php

namespace App\Http\Controllers;

use App\Actions\CorrectManualLedgerEntry;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Http\Requests\CorrectLedgerEntryRequest;
use App\Models\Category;
use App\Models\LedgerEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerEntryCorrectionController extends Controller
{
    public function edit(Request $request, int $ledgerEntry): Response
    {
        $entry = LedgerEntry::query()->whereBelongsTo($request->user())->findOrFail($ledgerEntry);
        abort_unless(in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true), 404);

        return Inertia::render('LedgerEntries/Edit', [
            'entry' => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'amount' => $entry->amount,
                'occurred_at' => $entry->occurred_at->toDateString(),
                'description' => $entry->description,
                'category_id' => $entry->category_id,
                'planning_type' => $entry->planning_type?->value,
            ],
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->where('type', $entry->type->value)
                ->where('status', RecordStatus::Active)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(CorrectLedgerEntryRequest $request, int $ledgerEntry, CorrectManualLedgerEntry $correct): RedirectResponse
    {
        $correct->handle($request->user(), $ledgerEntry, $request->validated());

        return to_route('ledger-entries.index')->with('success', 'Lançamento corrigido com histórico preservado.');
    }
}
