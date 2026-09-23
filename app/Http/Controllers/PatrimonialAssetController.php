<?php

namespace App\Http\Controllers;

use App\Actions\CreatePatrimonialAsset;
use App\Actions\DeletePatrimonialAsset;
use App\Actions\UpdatePatrimonialAsset;
use App\Http\Requests\StorePatrimonialAssetRequest;
use App\Http\Requests\UpdatePatrimonialAssetRequest;
use App\Queries\FinancialOverviewQuery;
use App\Queries\PatrimonyOverviewQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatrimonialAssetController extends Controller
{
    public function index(Request $request, FinancialOverviewQuery $financial, PatrimonyOverviewQuery $patrimony): Response
    {
        $balance = $financial->forUser($request->user(), 30)['general_balance'];

        return Inertia::render('Patrimony/Index', [
            'patrimony' => $patrimony->forUser($request->user(), $balance),
            'today' => now('America/Sao_Paulo')->toDateString(),
        ]);
    }

    public function store(StorePatrimonialAssetRequest $request, CreatePatrimonialAsset $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('patrimony.index')->with('success', 'Bem adicionado ao patrimônio estimado.');
    }

    public function update(UpdatePatrimonialAssetRequest $request, int $asset, UpdatePatrimonialAsset $update): RedirectResponse
    {
        $update->handle($request->user(), $asset, $request->validated());

        return to_route('patrimony.index')->with('success', 'Estimativa patrimonial atualizada.');
    }

    public function destroy(Request $request, int $asset, DeletePatrimonialAsset $delete): RedirectResponse
    {
        $delete->handle($request->user(), $asset);

        return to_route('patrimony.index')->with('success', 'Bem removido do patrimônio estimado.');
    }
}
