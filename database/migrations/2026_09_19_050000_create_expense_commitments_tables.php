<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 19, 2);
            $table->decimal('paid_amount', 19, 2)->default(0);
            $table->date('due_on');
            $table->string('planning_type', 32);
            $table->string('status', 16)->default('pending');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->unique(['user_id', 'operation_id'], 'expense_commitments_user_operation_unique');
            $table->index(['user_id', 'status', 'due_on'], 'expense_commitments_due_index');
        });

        Schema::create('expense_commitment_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_commitment_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 2);
            $table->date('paid_on');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->unique(['user_id', 'operation_id'], 'expense_commitment_payments_operation_unique');
            $table->unique('ledger_entry_id', 'expense_commitment_payments_ledger_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_commitment_payments');
        Schema::dropIfExists('expense_commitments');
    }
};
