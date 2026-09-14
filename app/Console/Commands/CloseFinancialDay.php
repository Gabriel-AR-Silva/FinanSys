<?php

namespace App\Console\Commands;

use App\Actions\CloseFinancialEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finansys:close-financial-day {--date= : Data de Brasilia a fechar no formato Y-m-d}')]
#[Description('Persiste o fechamento diario das visoes financeiras')]
class CloseFinancialDay extends Command
{
    public function handle(CloseFinancialEvaluation $closeEvaluation): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), 'America/Sao_Paulo')
            : CarbonImmutable::now('America/Sao_Paulo')->subDay();
        $count = 0;

        User::query()->orderBy('id')->eachById(function (User $user) use ($closeEvaluation, $date, &$count): void {
            $count += $closeEvaluation->handle($user, $date)->count();
        });

        $this->info("Avaliacoes persistidas: {$count}.");

        return self::SUCCESS;
    }
}
