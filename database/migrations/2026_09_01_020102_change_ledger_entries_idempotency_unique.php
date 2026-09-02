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
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropUnique('ledger_entries_idempotency_unique');
            $table->unique(
                ['user_id', 'operation_id', 'type'],
                'ledger_entries_idempotency_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropUnique('ledger_entries_idempotency_unique');
            $table->unique(
                ['user_id', 'operation_id', 'type', 'reference_type', 'reference_id'],
                'ledger_entries_idempotency_unique',
            );
        });
    }
};
