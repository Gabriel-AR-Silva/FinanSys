<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Models\CardPayment;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseCardPayment
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    public function handle(User $user, CardPayment $payment): void
    {
        DB::transaction(function () use ($user, $payment): void {
            $lockedPayment = CardPayment::query()
                ->whereBelongsTo($user)
                ->whereKey($payment->id)
                ->with([
                    'allocations.installment.advanceAllocations',
                    'chargeAllocations.charge',
                    'ledgerEntry',
                ])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->allocations->contains(
                fn ($allocation): bool => $allocation->installment->advanceAllocations->isNotEmpty(),
            )) {
                throw ValidationException::withMessages([
                    'payment' => 'Este pagamento alcança parcela que já foi antecipada depois. Desfaça a antecipação antes de alterar a fatura.',
                ]);
            }

            foreach ($lockedPayment->chargeAllocations as $allocation) {
                $charge = $allocation->charge()->lockForUpdate()->firstOrFail();
                $before = $charge->attributesToArray();
                $paid = BigDecimal::of($charge->paid_amount)->minus($allocation->amount);

                if ($paid->isNegative()) {
                    throw ValidationException::withMessages([
                        'payment' => 'Não foi possível reverter a fatura com segurança: um encargo ficaria com pagamento negativo.',
                    ]);
                }

                $charge->paid_amount = (string) $paid;
                $charge->status = $paid->isEqualTo($charge->amount)
                    ? CardInstallmentStatus::Paid
                    : CardInstallmentStatus::Pending;
                $charge->save();

                $this->auditRecorder->record($user, AuditAction::Updated, $charge, $before);
                $this->auditRecorder->record($user, AuditAction::Deleted, $allocation);
                $allocation->delete();
            }

            foreach ($lockedPayment->allocations as $allocation) {
                $installment = $allocation->installment()->lockForUpdate()->firstOrFail();
                $before = $installment->attributesToArray();
                $paid = BigDecimal::of($installment->paid_amount)->minus($allocation->amount);

                if ($paid->isNegative()) {
                    throw ValidationException::withMessages([
                        'payment' => 'Não foi possível reverter a fatura com segurança: uma parcela ficaria com pagamento negativo.',
                    ]);
                }

                $installment->paid_amount = (string) $paid;
                $installment->status = $paid->isEqualTo($installment->gross_amount)
                    ? CardInstallmentStatus::Paid
                    : CardInstallmentStatus::Pending;
                $installment->save();

                $this->auditRecorder->record($user, AuditAction::Updated, $installment, $before);
                $this->auditRecorder->record($user, AuditAction::Deleted, $allocation);
                $allocation->delete();
            }

            if ($lockedPayment->ledgerEntry) {
                $this->auditRecorder->record($user, AuditAction::Deleted, $lockedPayment->ledgerEntry);
                $lockedPayment->ledgerEntry->delete();
            }

            $this->auditRecorder->record($user, AuditAction::Deleted, $lockedPayment);
            $lockedPayment->delete();

            $this->refreshAlert->handle($user);
        }, 3);
    }
}
