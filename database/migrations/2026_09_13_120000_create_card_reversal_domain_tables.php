<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_purchase_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('credit_card_id');
            $table->unsignedBigInteger('card_purchase_id');
            $table->date('reversed_on');
            $table->decimal('cancelled_pending_amount', 19, 2)->default(0);
            $table->decimal('credited_paid_amount', 19, 2)->default(0);
            $table->string('reason')->nullable();
            $table->uuid('operation_id');
            $table->timestamps();
            $table->foreign(['credit_card_id', 'user_id'], 'cpr_credit_card_user_fk')->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
            $table->foreign(['card_purchase_id', 'user_id'], 'cpr_purchase_user_fk')->references(['id', 'user_id'])->on('card_purchases')->restrictOnDelete();
            $table->unique(['id', 'user_id'], 'cpr_id_user_uq');
            $table->unique(['user_id', 'operation_id'], 'cpr_user_operation_uq');
            $table->unique(['user_id', 'card_purchase_id'], 'cpr_user_purchase_uq');
            $table->index(['user_id', 'reversed_on'], 'cpr_user_reversed_idx');
        });

        Schema::create('card_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('credit_card_id');
            $table->unsignedBigInteger('card_purchase_reversal_id');
            $table->decimal('amount', 19, 2);
            $table->decimal('applied_amount', 19, 2)->default(0);
            $table->date('credited_on');
            $table->timestamps();
            $table->foreign(['credit_card_id', 'user_id'], 'ccredit_card_user_fk')->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
            $table->foreign(['card_purchase_reversal_id', 'user_id'], 'ccredit_reversal_user_fk')->references(['id', 'user_id'])->on('card_purchase_reversals')->cascadeOnDelete();
            $table->unique(['id', 'user_id'], 'ccredit_id_user_uq');
            $table->unique(['user_id', 'card_purchase_reversal_id'], 'ccredit_user_reversal_uq');
            $table->index(['user_id', 'credit_card_id', 'credited_on'], 'ccredit_user_card_date_idx');
        });

        Schema::create('card_credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('card_credit_id');
            $table->unsignedBigInteger('card_installment_id')->nullable();
            $table->unsignedBigInteger('card_charge_id')->nullable();
            $table->decimal('amount', 19, 2);
            $table->date('applied_on');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->foreign(['card_credit_id', 'user_id'], 'cca_credit_user_fk')->references(['id', 'user_id'])->on('card_credits')->restrictOnDelete();
            $table->foreign(['card_installment_id', 'user_id'], 'cca_installment_user_fk')->references(['id', 'user_id'])->on('card_installments')->restrictOnDelete();
            $table->foreign(['card_charge_id', 'user_id'], 'cca_charge_user_fk')->references(['id', 'user_id'])->on('card_charges')->restrictOnDelete();
            $table->unique(['id', 'user_id'], 'cca_id_user_uq');
            $table->unique(['user_id', 'operation_id'], 'cca_user_operation_uq');
            $table->index(['user_id', 'card_credit_id', 'applied_on'], 'cca_user_credit_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_credit_allocations');
        Schema::dropIfExists('card_credits');
        Schema::dropIfExists('card_purchase_reversals');
    }
};
