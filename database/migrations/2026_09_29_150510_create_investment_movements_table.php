<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_position_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('occurred_on');
            $table->decimal('quantity', 24, 8)->nullable();
            $table->decimal('unit_price', 19, 4)->nullable();
            $table->decimal('amount', 19, 2);
            $table->decimal('fees', 19, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on'], 'investment_movements_user_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_movements');
    }
};
