<?php

namespace App\Http\Controllers;

use App\Actions\TransferFunds;
use App\Domain\Ledger\ReferenceResolver;
use App\Enums\LedgerEntryReferenceType;
use App\Http\Requests\StoreTransferRequest;
use Illuminate\Http\RedirectResponse;

class TransferController extends Controller
{
    public function store(StoreTransferRequest $request, ReferenceResolver $references, TransferFunds $transfer): RedirectResponse
    {
        $data = $request->validated();
        $source = $references->resolveActive($request->user(), LedgerEntryReferenceType::from($data['source_type']), (int) $data['source_id'], 'source_id');
        $destination = $references->resolveActive($request->user(), LedgerEntryReferenceType::from($data['destination_type']), (int) $data['destination_id'], 'destination_id');

        $transfer->handle($request->user(), $source, $destination, $data['amount'], $data['operation_id']);

        return to_route('ledger-entries.index')->with('success', 'Transferência realizada com sucesso.');
    }
}
