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
            $table->foreignId('card_purchase_id')->constrained()->restrictOnDelete();
            $table->decimal('released_pending_amount', 19, 2);
            $table->decimal('credit_amount', 19, 2);
            $table->date('reversed_on');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->unique(['user_id', 'card_purchase_id']);
            $table->unique(['user_id', 'operation_id']);
        });

        Schema::create('card_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_purchase_reversal_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 19, 2);
            $table->decimal('remaining_amount', 19, 2);
            $table->timestamps();
        });

        Schema::create('card_credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_credit_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('card_installment_id');
            $table->decimal('amount', 19, 2);
            $table->uuid('operation_id');
            $table->timestamps();
            $table->foreign(['card_installment_id', 'user_id'])->references(['id', 'user_id'])->on('card_installments')->restrictOnDelete();
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_credit_allocations');
        Schema::dropIfExists('card_credits');
        Schema::dropIfExists('card_purchase_reversals');
    }
};
