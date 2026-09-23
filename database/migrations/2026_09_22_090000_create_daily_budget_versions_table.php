<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 19, 2);
            // Store UTC instants; interpret the day boundary in America/Sao_Paulo.
            $table->dateTime('effective_at');
            $table->dateTime('recorded_at');
            $table->string('origin', 32);
            $table->string('reason')->nullable();
            $table->uuid('operation_id');

            // A future check-in can enforce that its version belongs to its user.
            $table->unique(['id', 'user_id'], 'daily_budget_id_user_unique');
            $table->unique(['user_id', 'operation_id'], 'daily_budget_user_operation_unique');
            $table->index(['user_id', 'effective_at', 'id'], 'daily_budget_user_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_budget_versions');
    }
};
