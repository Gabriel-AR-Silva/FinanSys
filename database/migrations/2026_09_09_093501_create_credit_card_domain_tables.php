<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('credit_cards')) {
            Schema::create('credit_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedTinyInteger('closing_day');
                $table->unsignedTinyInteger('due_day');
                $table->string('status', 32)->default('active');
                $table->uuid('operation_id');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['id', 'user_id']);
                $table->unique(['user_id', 'operation_id']);
            });
        }

        if (! Schema::hasTable('card_purchases')) {
            Schema::create('card_purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('credit_card_id');
                $table->foreignId('category_id')->constrained()->restrictOnDelete();
                $table->string('description');
                $table->string('planning_type', 24);
                $table->decimal('gross_amount', 19, 2);
                $table->date('purchased_on');
                $table->unsignedSmallInteger('installments_count');
                $table->uuid('operation_id');
                $table->timestamps();
                $table->softDeletes();
                $table->foreign(['credit_card_id', 'user_id'])->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
                $table->unique(['id', 'user_id']);
                $table->unique(['user_id', 'operation_id']);
            });
        }

        if (! Schema::hasTable('card_installments')) {
            Schema::create('card_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('card_purchase_id');
                $table->unsignedSmallInteger('installment_number');
                $table->decimal('gross_amount', 19, 2);
                $table->decimal('paid_amount', 19, 2)->default(0);
                $table->date('due_on');
                $table->date('original_due_on');
                $table->string('status', 16)->default('pending');
                $table->timestamps();
                $table->foreign(['card_purchase_id', 'user_id'])->references(['id', 'user_id'])->on('card_purchases')->cascadeOnDelete();
                $table->unique(['id', 'user_id']);
                $table->unique(['card_purchase_id', 'installment_number']);
                $table->index(['user_id', 'due_on', 'status']);
            });
        }

        if (! Schema::hasTable('card_payments')) {
            Schema::create('card_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('credit_card_id');
                $table->unsignedBigInteger('source_account_id');
                $table->unsignedBigInteger('ledger_entry_id');
                $table->decimal('amount', 19, 2);
                $table->date('paid_on');
                $table->uuid('operation_id');
                $table->timestamps();
                $table->foreign(['credit_card_id', 'user_id'])->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
                $table->foreign(['source_account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
                $table->foreign(['ledger_entry_id', 'user_id'])->references(['id', 'user_id'])->on('ledger_entries')->restrictOnDelete();
                $table->unique(['id', 'user_id']);
                $table->unique(['user_id', 'operation_id']);
                $table->unique(['user_id', 'ledger_entry_id']);
            });
        }

        if (! Schema::hasTable('card_payment_allocations')) {
            Schema::create('card_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('card_payment_id');
                $table->unsignedBigInteger('card_installment_id');
                $table->decimal('amount', 19, 2);
                $table->timestamps();
                $table->foreign(['card_payment_id', 'user_id'])->references(['id', 'user_id'])->on('card_payments')->cascadeOnDelete();
                $table->foreign(['card_installment_id', 'user_id'])->references(['id', 'user_id'])->on('card_installments')->restrictOnDelete();
                $table->unique(
                    ['card_payment_id', 'card_installment_id'],
                    'card_payment_installment_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_payment_allocations');
        Schema::dropIfExists('card_payments');
        Schema::dropIfExists('card_installments');
        Schema::dropIfExists('card_purchases');
        Schema::dropIfExists('credit_cards');
    }
};
