<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_advance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_advance_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('card_installment_id');
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('discount_amount', 19, 2);
            $table->decimal('net_amount', 19, 2);
            $table->timestamps();
            $table->foreign(['card_installment_id', 'user_id'])->references(['id', 'user_id'])->on('card_installments')->restrictOnDelete();
            $table->unique(['card_advance_id', 'card_installment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_advance_allocations');
    }
};
