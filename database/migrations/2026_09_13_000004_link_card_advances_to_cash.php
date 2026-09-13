<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_advances', function (Blueprint $table) {
            $table->unsignedBigInteger('source_account_id')->after('credit_card_id');
            $table->unsignedBigInteger('ledger_entry_id')->after('source_account_id');
            $table->foreign(['source_account_id', 'user_id'])->references(['id', 'user_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['ledger_entry_id', 'user_id'])->references(['id', 'user_id'])->on('ledger_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('card_advances', function (Blueprint $table) {
            $table->dropForeign(['source_account_id', 'user_id']);
            $table->dropForeign(['ledger_entry_id', 'user_id']);
            $table->dropColumn(['source_account_id', 'ledger_entry_id']);
        });
    }
};
