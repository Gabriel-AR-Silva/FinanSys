<?php

namespace App\Console\Commands;

use App\Actions\CloseFinancialEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finansys:rebuild-financial-days {--from= : Primeiro dia de Brasilia no formato Y-m-d} {--to= : Ultimo dia de Brasilia no formato Y-m-d}')]
#[Description('Reconstrói avaliações financeiras ausentes sem sobrescrever fechamentos registrados')]
class RebuildFinancialDays extends Command
{
    public function handle(CloseFinancialEvaluation $closeEvaluation): int
    {
        try {
            $from = $this->dateOption('from', CarbonImmutable::now('America/Sao_Paulo')->subDay());
            $to = $this->dateOption('to', $from);
        } catch (InvalidFormatException) {
            $this->error('Informe datas válidas no formato Y-m-d.');

            return self::INVALID;
        }

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            $this->error('O intervalo deve estar em ordem e ter no máximo 367 dias.');

            return self::INVALID;
        }

        $count = 0;
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            User::query()->orderBy('id')->eachById(function (User $user) use ($closeEvaluation, $date, &$count): void {
                $count += $closeEvaluation->handle($user, $date, true)->count();
            });
        }

        $this->info("Avaliacoes processadas: {$count}.");

        return self::SUCCESS;
    }

    private function dateOption(string $name, CarbonImmutable $default): CarbonImmutable
    {
        $value = $this->option($name);
        if ($value === null) {
            return $default;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', (string) $value, 'America/Sao_Paulo');
    }
}
