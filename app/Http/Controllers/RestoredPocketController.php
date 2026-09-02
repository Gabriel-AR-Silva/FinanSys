<?php

namespace App\Http\Controllers;

use App\Actions\RestorePocket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestoredPocketController extends Controller
{
    public function store(Request $request, int $pocket, RestorePocket $restorePocket): RedirectResponse
    {
        $restorePocket->handle($request->user(), $pocket);

        return to_route('pockets.index')->with('success', 'Caixinha restaurada com sucesso.');
    }
}
