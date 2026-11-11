<?php

namespace App\Http\Controllers;

use App\Models\NfcAccessLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NfcShareLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $token = Str::random(64);
        NfcAccessLink::query()->create([
            'user_id' => $request->user()->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        return to_route('nfc.index')->with(
            'nfc_share_url',
            route('nfc.share.index', ['token' => $token]),
        );
    }

    public function destroy(Request $request, int $link): RedirectResponse
    {
        $record = NfcAccessLink::query()->whereBelongsTo($request->user())->findOrFail($link);
        $record->update(['revoked_at' => now()]);

        return to_route('nfc.index');
    }
}
