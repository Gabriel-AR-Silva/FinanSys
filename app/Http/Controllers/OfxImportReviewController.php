<?php

namespace App\Http\Controllers;

use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfxImportReviewController extends Controller
{
    public function update(Request $request, OfxImportItem $item): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $item->user_id !== (int) $user->getKey()) {
            abort(404);
        }

        return back();
    }
}
