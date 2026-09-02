<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Pocket;
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
        //
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

        DevCommands::artisan('queue:work --sleep=1 --tries=3 --timeout=60 --max-jobs=100', 'queue');

        Relation::enforceMorphMap([
            'account' => Account::class,
            'category' => Category::class,
            'pocket' => Pocket::class,
            'ledger_entry' => LedgerEntry::class,
        ]);

        Vite::prefetch(concurrency: 3);
    }
}
