<?php

namespace App\Providers;

use App\Contracts\MailtrapSandboxSender;
use App\Models\Account;
use App\Models\CardInstallment;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\EssentialBudget;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\Pocket;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\SocialIdentity;
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
            'account' => Account::class,
            'category' => Category::class,
            'pocket' => Pocket::class,
            'ledger_entry' => LedgerEntry::class,
            'social_identity' => SocialIdentity::class,
            'monthly_financial_setting' => MonthlyFinancialSetting::class,
            'essential_budget' => EssentialBudget::class,
            'expense_refund' => ExpenseRefund::class,
            'receipt_forecast' => ReceiptForecast::class,
            'receipt_forecast_link' => ReceiptForecastLink::class,
            'credit_card' => CreditCard::class,
            'card_purchase' => CardPurchase::class,
            'card_installment' => CardInstallment::class,
            'card_payment' => CardPayment::class,
            'card_payment_allocation' => CardPaymentAllocation::class,
        ]);

        Vite::prefetch(concurrency: 3);
    }
}
