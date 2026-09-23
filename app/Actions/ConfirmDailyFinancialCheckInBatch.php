<?php

namespace App\Actions;

use App\Models\DailyFinancialCheckIn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmDailyFinancialCheckInBatch
{
    public function __construct(
        private RecordDailyFinancialCheckIn $recorder,
    ) {}

    /**
     * @param list<array{date:string,operation_id:string,reason?:?string}> $items
     * @return Collection<int, DailyFinancialCheckIn>
     */
    public function handle(User $actor, array $items): Collection
    {
        if ($items === [] || count($items) > 31) {
            throw ValidationException::withMessages(['days' => 'Selecione entre 1 e 31 dias para confirmar.']);
        }

        $dates = [];
        $operations = [];
        foreach ($items as $item) {
            if (is_array($item) === false || isset($item['date'], $item['operation_id']) === false) {
                throw ValidationException::withMessages(['days' => 'Cada dia precisa de data e chave de operação.']);
            }
            if (isset($dates[$item['date']]) || isset($operations[$item['operation_id']])) {
                throw ValidationException::withMessages(['days' => 'Não repita dias nem chaves de operação no lote.']);
            }
            $dates[$item['date']] = true;
            $operations[$item['operation_id']] = true;
        }

        return DB::transaction(function () use ($actor, $items): Collection {
            return collect($items)->map(fn (array $item): DailyFinancialCheckIn => $this->recorder->confirm(
                $actor,
                $item['date'],
                $item['operation_id'],
                $item['reason'] ?? null,
            ));
        }, 3);
    }
}
