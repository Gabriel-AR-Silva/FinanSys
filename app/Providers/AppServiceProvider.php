<?php

namespace App\Providers;

use App\Contracts\MailtrapSandboxSender;
use App\Models\Account;
use App\Models\CardAdvance;
use App\Models\CardAdvanceAllocation;
use App\Models\CardCharge;
use App\Models\CardChargePaymentAllocation;
use App\Models\CardCredit;
use App\Models\CardCreditAllocation;
use App\Models\CardInstallment;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\DailyBudgetVersion;
use App\Models\DailyFinancialCheckIn;
use App\Models\EssentialBudget;
use App\Models\ExpenseRefund;
use App\Models\FinancialGoal;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\Pocket;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\MailtrapApiSandboxSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailtrapSandboxSender::class, MailtrapApiSandboxSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('password-reset', function (Request $request): array {
            $email = Str::lower($request->string('email')->toString());

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('google-auth', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        DevCommands::artisan('queue:work --sleep=1 --tries=3 --timeout=60 --max-jobs=100', 'queue');

        Relation::enforceMorphMap([
            'user' => User::class,
            'account' => Account::class,
            'category' => Category::class,
            'pocket' => Pocket::class,
            'ledger_entry' => LedgerEntry::class,
            'social_identity' => SocialIdentity::class,
            'monthly_financial_setting' => MonthlyFinancialSetting::class,
            'daily_budget_version' => DailyBudgetVersion::class,
            'daily_financial_check_in' => DailyFinancialCheckIn::class,
            'essential_budget' => EssentialBudget::class,
            'expense_refund' => ExpenseRefund::class,
            'financial_goal' => FinancialGoal::class,
            'receipt_forecast' => ReceiptForecast::class,
            'receipt_forecast_link' => ReceiptForecastLink::class,
            'credit_card' => CreditCard::class,
            'card_purchase' => CardPurchase::class,
            'card_installment' => CardInstallment::class,
            'card_advance' => CardAdvance::class,
            'card_advance_allocation' => CardAdvanceAllocation::class,
            'card_charge' => CardCharge::class,
            'card_charge_payment_allocation' => CardChargePaymentAllocation::class,
            'card_payment' => CardPayment::class,
            'card_payment_allocation' => CardPaymentAllocation::class,
            'card_purchase_reversal' => CardPurchaseReversal::class,
            'card_credit' => CardCredit::class,
            'card_credit_allocation' => CardCreditAllocation::class,
        ]);

        Vite::prefetch(concurrency: 3);
    }
}
