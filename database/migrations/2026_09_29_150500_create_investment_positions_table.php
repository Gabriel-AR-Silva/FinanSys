<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 60);
            $table->string('ticker', 40)->nullable();
            $table->string('name', 120);
            $table->decimal('quantity', 24, 8);
            $table->decimal('average_cost', 19, 4);
            $table->decimal('total_invested', 19, 2);
            $table->decimal('current_value', 19, 2)->nullable();
            $table->string('valuation_source', 40)->nullable();
            $table->date('valued_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asset_type'], 'investment_positions_user_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_positions');
    }
};
