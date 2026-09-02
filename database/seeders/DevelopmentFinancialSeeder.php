<?php

namespace Database\Seeders;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentFinancialSeeder extends Seeder
{
    private const DEMO_MARKER = 'c4e42d8d-b5b5-4c8a-82aa-674fdc74c200';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentFinancialSeeder só pode executar em local ou testing.');
        }

        $user = User::query()->where('email', config('development.user.email'))->firstOrFail();
        if ($user->accounts()->withTrashed()->where('operation_id', self::DEMO_MARKER)->exists()) {
            $this->categorizeEntries($user);
            $this->command?->info('Cenário financeiro demonstrativo já existe.');

            return;
        }

        DB::transaction(function () use ($user): void {
            $principal = Account::factory()->for($user)->create([
                'name' => 'Conta principal',
                'operation_id' => self::DEMO_MARKER,
            ]);
            $wallet = Account::factory()->for($user)->create([
                'name' => 'Carteira digital',
                'operation_id' => 'fa8ebca0-09bf-4669-b1d6-929341b7b809',
            ]);
            $reserve = Pocket::factory()->for($user)->for($principal)->create(['name' => 'Reserva de emergência']);
            $travel = Pocket::factory()->for($user)->for($principal)->create(['name' => 'Viagem']);
            $start = CarbonImmutable::now()->startOfMonth()->subMonths(13);

            $this->entry($user, $principal, LedgerEntryType::OpeningBalance, '4200.00', $start, 'Saldo inicial demonstrativo');
            $this->entry($user, $wallet, LedgerEntryType::OpeningBalance, '350.00', $start, 'Saldo inicial da carteira');

            for ($month = 0; $month < 14; $month++) {
                $date = $start->addMonths($month);
                $this->entry($user, $principal, LedgerEntryType::Income, (string) (4800 + ($month * 35)), $date->day(5), 'Salário');
                $this->entry($user, $principal, LedgerEntryType::Expense, (string) (1580 + ($month * 8)), $date->day(8), 'Moradia');
                $this->entry($user, $principal, LedgerEntryType::Expense, (string) (620 + (($month % 4) * 45)), $date->day(12), 'Mercado e alimentação');
                $this->entry($user, $principal, LedgerEntryType::Expense, (string) (210 + (($month % 3) * 20)), $date->day(18), 'Serviços e assinaturas');
                $this->entry($user, $wallet, LedgerEntryType::Expense, (string) (180 + (($month % 5) * 30)), $date->day(22), 'Lazer');

                if ($month % 2 === 0) {
                    $this->entry($user, $wallet, LedgerEntryType::Income, (string) (650 + ($month * 15)), $date->day(15), 'Projeto freelance');
                }

                $this->transfer($user, $principal, $month % 2 === 0 ? $reserve : $travel, '300.00', $date->day(20));
            }

            for ($day = 1; $day <= 60; $day += 3) {
                $date = CarbonImmutable::now()->startOfDay()->subDays($day);
                $type = $day % 2 === 0 ? LedgerEntryType::Income : LedgerEntryType::Expense;
                $amount = $type === LedgerEntryType::Income ? (string) (90 + $day) : (string) (45 + ($day % 9) * 8);
                $this->entry($user, $wallet, $type, $amount, $date, $type === LedgerEntryType::Income ? 'Entrada extra' : 'Despesa cotidiana');
            }
        });

        $this->command?->info('Cenário financeiro demonstrativo criado com factories.');
    }

    private function entry(User $user, Account|Pocket $reference, LedgerEntryType $type, string $amount, CarbonImmutable $date, string $description, ?string $operationId = null): LedgerEntry
    {
        $factory = match ($type) {
            LedgerEntryType::OpeningBalance => LedgerEntry::factory()->openingBalance(),
            LedgerEntryType::Income => LedgerEntry::factory()->income(),
            LedgerEntryType::Expense => LedgerEntry::factory()->expense(),
            default => LedgerEntry::factory()->state(['type' => $type]),
        };

        return $factory->create([
            'user_id' => $user->id,
            'category_id' => $this->categoryId($user, $description),
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'amount' => $amount,
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'occurred_at' => $date,
            'description' => $description,
        ]);
    }

    private function categorizeEntries(User $user): void
    {
        $user->ledgerEntries()->whereNull('category_id')->each(function (LedgerEntry $entry) use ($user): void {
            $categoryId = $this->categoryId($user, $entry->description);

            if ($categoryId !== null) {
                $entry->update(['category_id' => $categoryId]);
            }
        });
    }

    private function categoryId(User $user, ?string $description): ?int
    {
        $categoryName = match ($description) {
            'Salário' => 'Salário',
            'Moradia' => 'Moradia',
            'Mercado e alimentação' => 'Alimentação',
            'Serviços e assinaturas' => 'Assinaturas',
            'Lazer' => 'Lazer',
            'Projeto freelance' => 'Freelance',
            'Entrada extra' => 'Outras receitas',
            'Despesa cotidiana' => 'Outras despesas',
            default => null,
        };

        return $categoryName === null
            ? null
            : Category::query()->whereBelongsTo($user)->where('name', $categoryName)->value('id');
    }

    private function transfer(User $user, Account $source, Pocket $destination, string $amount, CarbonImmutable $date): void
    {
        $operationId = (string) Str::uuid();
        $this->entry($user, $source, LedgerEntryType::TransferOut, $amount, $date, 'Transferência para '.$destination->name, $operationId);
        $this->entry($user, $destination, LedgerEntryType::TransferIn, $amount, $date, 'Transferência da '.$source->name, $operationId);
    }
}
