<?php

namespace App\Http\Controllers;

use App\Actions\ManageNfcRecords;
use App\Models\NfcAccessLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NfcShareLinkController extends Controller
{
    public function store(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $token = Str::random(64);
        DB::transaction(function () use ($request, $records, $token): void {
            $link = NfcAccessLink::query()->create([
                'user_id' => $request->user()->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);
            $records->recordAccessEvent($request->user(), 'created', $link);
        }, 3);

        return to_route('nfc.index')->with(
            'nfc_share_url',
            route('nfc.share.index', ['token' => $token]),
        );
    }

    public function destroy(Request $request, ManageNfcRecords $records, int $link): RedirectResponse
    {
        DB::transaction(function () use ($request, $records, $link): void {
            $record = NfcAccessLink::query()->whereBelongsTo($request->user())
                ->lockForUpdate()->findOrFail($link);
            if ($record->revoked_at === null) {
                $record->update(['revoked_at' => now()]);
                $records->recordAccessEvent($request->user(), 'revoked', $record);
            }
        }, 3);

        return to_route('nfc.index');
    }
}
