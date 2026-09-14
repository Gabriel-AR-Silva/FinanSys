<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetOperationalFinancialData
{
    /** @return array{ledger_entries:int,receipt_forecasts:int,card_operations:int,ofx_imports:int,derived_records:int} */
    public function preview(User $user): array
    {
        $userId = $user->getKey();

        return [
            'ledger_entries' => DB::table('ledger_entries')->where('user_id', $userId)->count(),
            'receipt_forecasts' => DB::table('receipt_forecasts')->where('user_id', $userId)->count(),
            'card_operations' => $this->cardOperationsCount($userId),
            'ofx_imports' => DB::table('bank_statement_imports')->where('user_id', $userId)->count(),
            'derived_records' => DB::table('financial_evaluations')->where('user_id', $userId)->count()
                + DB::table('internal_alerts')->where('user_id', $userId)->count(),
        ];
    }

    /** @return array{ledger_entries:int,receipt_forecasts:int,card_operations:int,ofx_imports:int,derived_records:int} */
    public function handle(User $user): array
    {
        $preview = $this->preview($user);

        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $userId = $user->getKey();

            foreach ($this->deletionOrder() as $table) {
                DB::table($table)->where('user_id', $userId)->delete();
            }

            DB::table('audit_logs')
                ->where('user_id', $userId)
                ->whereIn('auditable_type', $this->operationalAuditTypes())
                ->delete();
            DB::table('audit_logs')
                ->where('user_id', $userId)
                ->where('auditable_type', 'user')
                ->where('auditable_id', $userId)
                ->where('action', AuditAction::Purged->value)
                ->delete();

            AuditLog::query()->create([
                'user_id' => $userId,
                'action' => AuditAction::Purged->value,
                'auditable_type' => $user->getMorphClass(),
                'auditable_id' => $userId,
                'before' => null,
                'after' => [
                    'scope' => 'operational_financial_data',
                    'preserved' => ['identity', 'categories', 'accounts', 'pockets', 'credit_cards', 'financial_settings'],
                ],
                'created_at' => now(),
            ]);
        }, 3);

        return $preview;
    }

    private function cardOperationsCount(int $userId): int
    {
        return collect([
            'card_purchases',
            'card_installments',
            'card_payments',
            'card_payment_allocations',
            'card_charges',
            'card_charge_payment_allocations',
            'card_advances',
            'card_advance_allocations',
            'card_purchase_reversals',
            'card_credits',
            'card_credit_allocations',
        ])->sum(fn (string $table): int => DB::table($table)->where('user_id', $userId)->count());
    }

    /** @return list<string> */
    private function operationalAuditTypes(): array
    {
        return [
            'ledger_entry',
            'expense_refund',
            'receipt_forecast',
            'receipt_forecast_link',
            'card_purchase',
            'card_installment',
            'card_advance',
            'card_advance_allocation',
            'card_charge',
            'card_charge_payment_allocation',
            'card_payment',
            'card_payment_allocation',
            'card_purchase_reversal',
            'card_credit',
            'card_credit_allocation',
        ];
    }

    /** @return list<string> */
    private function deletionOrder(): array
    {
        return [
            'bank_statement_import_items',
            'bank_statement_imports',
            'card_credit_allocations',
            'card_credits',
            'card_purchase_reversals',
            'card_charge_payment_allocations',
            'card_payment_allocations',
            'card_advance_allocations',
            'card_advances',
            'card_payments',
            'card_charges',
            'card_installments',
            'card_purchases',
            'receipt_forecast_link_operations',
            'receipt_forecast_links',
            'expense_refunds',
            'internal_alerts',
            'financial_evaluations',
            'receipt_forecasts',
            'ledger_entries',
        ];
    }
}
