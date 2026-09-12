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
        Schema::table('receipt_forecasts', function (Blueprint $table) {
            $table->unique(['id', 'user_id'], 'receipt_forecasts_owner_unique');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->unique(['id', 'user_id'], 'ledger_entries_owner_unique');
        });

        Schema::create('receipt_forecast_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('receipt_forecast_id');
            $table->unsignedBigInteger('ledger_entry_id');
            $table->uuid('operation_id');
            $table->timestamp('linked_at');
            $table->timestamp('unlinked_at')->nullable();
            $table->string('unlink_reason', 32)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign(['receipt_forecast_id', 'user_id'], 'receipt_forecast_links_forecast_owner_fk')
                ->references(['id', 'user_id'])->on('receipt_forecasts')->cascadeOnDelete();
            $table->foreign(['ledger_entry_id', 'user_id'], 'receipt_forecast_links_entry_owner_fk')
                ->references(['id', 'user_id'])->on('ledger_entries')->cascadeOnDelete();
            $table->unique(['user_id', 'ledger_entry_id']);
            $table->unique(['user_id', 'operation_id']);
            $table->index(['user_id', 'receipt_forecast_id', 'unlinked_at', 'id'], 'receipt_forecast_links_progress_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_forecast_links');

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropUnique('ledger_entries_owner_unique');
        });

        Schema::table('receipt_forecasts', function (Blueprint $table) {
            $table->dropUnique('receipt_forecasts_owner_unique');
        });
    }
};
