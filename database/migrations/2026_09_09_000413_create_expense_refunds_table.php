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
        Schema::create('expense_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('expense_ledger_entry_id');
            $table->unsignedBigInteger('refund_ledger_entry_id');
            $table->uuid('operation_id');
            $table->timestamps();

            $table->foreign(['expense_ledger_entry_id', 'user_id'], 'expense_refunds_expense_owner_fk')
                ->references(['id', 'user_id'])->on('ledger_entries')->cascadeOnDelete();
            $table->foreign(['refund_ledger_entry_id', 'user_id'], 'expense_refunds_refund_owner_fk')
                ->references(['id', 'user_id'])->on('ledger_entries')->cascadeOnDelete();
            $table->unique(['user_id', 'operation_id']);
            $table->unique(['user_id', 'refund_ledger_entry_id']);
            $table->index(['user_id', 'expense_ledger_entry_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_refunds');
    }
};
