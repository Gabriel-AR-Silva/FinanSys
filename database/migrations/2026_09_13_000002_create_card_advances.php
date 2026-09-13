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
            $table->foreignId('credit_card_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('discount_amount', 19, 2);
            $table->decimal('net_amount', 19, 2);
            $table->date('advanced_on');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_advances');
    }
};
