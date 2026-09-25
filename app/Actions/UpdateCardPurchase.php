<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\RecordStatus;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Queries\CreditCardLimitQuery;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCardPurchase
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
        private CreditCardLimitQuery $cardLimits,
    ) {}

    public function handle(User $user, CardPurchase $purchase, array $data): CardPurchase
    {
        return DB::transaction(function () use ($user, $purchase, $data): CardPurchase {
            $locked = CardPurchase::query()
                ->whereBelongsTo($user)
                ->whereKey($purchase->id)
                ->with(['installments.allocations', 'installments.advanceAllocations'])
                ->lockForUpdate()
                ->firstOrFail();

            $hasHistory = $locked->installments->contains(
                fn ($installment): bool => BigDecimal::of($installment->paid_amount)->isPositive()
                    || $installment->allocations->isNotEmpty()
                    || $installment->advanceAllocations->isNotEmpty(),
            );
            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'purchase' => 'Essa compra já possui pagamento ou antecipação. Desfaça essas operações antes de editar a compra.',
                ]);
            }

            $amount = $this->money($data['gross_amount']);
            $count = (int) $data['installments_count'];
            $paidCount = (int) ($data['paid_installments_count'] ?? 0);
            if ($count < 1 || $count > 120 || $paidCount < 0 || $paidCount >= $count) {
                throw ValidationException::withMessages(['installments_count' => 'Revise a quantidade total e as parcelas já pagas.']);
            }

            $purchasedOn = $this->date($data['purchased_on'], 'purchased_on');
            $firstDueOn = $this->date($data['first_due_on'], 'first_due_on');
            $description = trim($data['description']);
            $planningType = ExpensePlanningType::tryFrom($data['planning_type']);
            if ($description === '' || mb_strlen($description) > 255 || ! in_array($planningType, [ExpensePlanningType::Ordinary, ExpensePlanningType::Extraordinary], true)) {
                throw ValidationException::withMessages(['description' => 'Revise a descrição e o tipo de planejamento.']);
            }

            $card = CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['credit_card_id'])->lockForUpdate()->first();
            $category = Category::query()->whereBelongsTo($user)->where('type', CategoryType::Expense)->where('status', RecordStatus::Active)->whereKey($data['category_id'])->lockForUpdate()->first();
            if (! $card) throw ValidationException::withMessages(['credit_card_id' => 'Escolha um cartão disponível.']);
            if (! $category) throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de despesa disponível.']);

            $amounts = $this->split($amount, $count);
            $remainingAmounts = array_slice($amounts, $paidCount);
            $remaining = array_reduce($remainingAmounts, fn (BigDecimal $carry, string $value): BigDecimal => $carry->plus($value), BigDecimal::zero()->toScale(2));

            if ($card->credit_limit !== null) {
                $available = BigDecimal::of($this->cardLimits->forCard($card)['available'] ?? '0.00');
                if ($card->id === $locked->credit_card_id) {
                    $currentOutstanding = $locked->installments->reduce(
                        fn (BigDecimal $total, $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
                        BigDecimal::zero()->toScale(2),
                    );
                    $available = $available->plus($currentOutstanding);
                }
                if ($remaining->isGreaterThan($available)) {
                    throw ValidationException::withMessages(['gross_amount' => 'O saldo restante da compra ultrapassa o limite disponível deste cartão.']);
                }
            }

            $before = $locked->attributesToArray();
            foreach ($locked->installments as $installment) {
                $this->auditRecorder->record($user, AuditAction::Deleted, $installment);
            }
            $locked->installments()->delete();

            $locked->fill([
                'credit_card_id' => $card->id,
                'category_id' => $category->id,
                'description' => $description,
                'planning_type' => $planningType,
                'gross_amount' => (string) $amount,
                'purchased_on' => $purchasedOn->toDateString(),
                'installments_count' => $count,
            ])->save();
            $this->auditRecorder->record($user, AuditAction::Updated, $locked, $before);

            foreach ($remainingAmounts as $index => $installmentAmount) {
                $dueOn = $this->installmentDate($firstDueOn, $index);
                $installment = $locked->installments()->create([
                    'user_id' => $user->id,
                    'installment_number' => $paidCount + $index + 1,
                    'gross_amount' => $installmentAmount,
                    'paid_amount' => '0.00',
                    'due_on' => $dueOn,
                    'original_due_on' => $dueOn,
                    'status' => CardInstallmentStatus::Pending,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $installment);
            }

            $this->refreshAlert->handle($user);

            return $locked->load('installments');
        }, 3);
    }

    private function split(BigDecimal $amount, int $count): array
    {
        $base = $amount->dividedBy($count, 2, RoundingMode::Floor);
        $remainderCents = (int) (string) $amount->minus($base->multipliedBy($count))->multipliedBy(100);
        return array_map(fn (int $index): string => (string) ($index < $remainderCents ? $base->plus('0.01') : $base), range(0, $count - 1));
    }

    private function installmentDate(CarbonImmutable $firstDueOn, int $offset): string
    {
        $month = $firstDueOn->startOfMonth()->addMonths($offset);
        if ($month->year > 9999) throw ValidationException::withMessages(['first_due_on' => 'As parcelas ultrapassam o calendário suportado.']);
        return $month->day(min($firstDueOn->day, $month->daysInMonth))->toDateString();
    }

    private function money(string $value): BigDecimal
    {
        if (! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $value)) throw ValidationException::withMessages(['gross_amount' => 'Informe um valor positivo com até duas casas decimais.']);
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if (! $amount->isPositive()) throw ValidationException::withMessages(['gross_amount' => 'O valor deve ser maior que zero.']);
        return $amount;
    }

    private function date(string $value, string $field): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'America/Sao_Paulo');
        if ($date === false || $date->format('Y-m-d') !== $value) throw ValidationException::withMessages([$field => 'Informe uma data válida.']);
        return $date;
    }
}
