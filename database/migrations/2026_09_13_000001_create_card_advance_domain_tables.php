<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('credit_card_id');
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('discount_amount', 19, 2);
            $table->decimal('net_amount', 19, 2);
            $table->date('advanced_on');
            $table->json('selected_installment_ids');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->foreign(['credit_card_id', 'user_id'])->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
            $table->foreign(['source_account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['ledger_entry_id', 'user_id'])->references(['id', 'user_id'])->on('ledger_entries')->restrictOnDelete();
            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'operation_id']);
            $table->unique(['user_id', 'ledger_entry_id']);
            $table->index(['user_id', 'advanced_on']);
        });

        Schema::create('card_advance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('card_advance_id');
            $table->unsignedBigInteger('card_installment_id');
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('discount_amount', 19, 2);
            $table->decimal('net_amount', 19, 2);
            $table->date('original_due_on');
            $table->timestamps();
            $table->foreign(['card_advance_id', 'user_id'])->references(['id', 'user_id'])->on('card_advances')->cascadeOnDelete();
            $table->foreign(['card_installment_id', 'user_id'])->references(['id', 'user_id'])->on('card_installments')->restrictOnDelete();
            $table->unique(['card_advance_id', 'card_installment_id']);
            $table->unique(['user_id', 'card_installment_id']);
            $table->index(['user_id', 'card_installment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_advance_allocations');
        Schema::dropIfExists('card_advances');
    }
};
