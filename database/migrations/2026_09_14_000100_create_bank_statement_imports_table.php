<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_statement_imports')) {
            Schema::create('bank_statement_imports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->string('institution', 160);
                $table->string('institution_id', 80)->nullable();
                $table->string('currency', 3)->default('BRL');
                $table->char('source_account_hash', 64);
                $table->string('source_account_suffix', 12);
                $table->dateTimeTz('period_start');
                $table->dateTimeTz('period_end');
                $table->decimal('ledger_balance', 18, 2)->nullable();
                $table->dateTimeTz('ledger_balance_at')->nullable();
                $table->string('status', 32)->default('pending_review');
                $table->unsignedInteger('transaction_count')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'account_id', 'created_at'], 'bank_import_user_account_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_imports');
    }
};
