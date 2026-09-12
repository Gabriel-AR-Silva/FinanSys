<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receipt_forecast_links', function (Blueprint $table) {
            $table->unique(['id', 'user_id'], 'receipt_forecast_links_owner_unique');
        });

        Schema::create('receipt_forecast_link_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('receipt_forecast_link_id');
            $table->unsignedBigInteger('receipt_forecast_id');
            $table->unsignedBigInteger('ledger_entry_id');
            $table->uuid('operation_id');
            $table->string('kind', 16);
            $table->timestamps();

            $table->foreign(['receipt_forecast_link_id', 'user_id'], 'receipt_link_operations_link_owner_fk')
                ->references(['id', 'user_id'])->on('receipt_forecast_links')->cascadeOnDelete();
            $table->foreign(['receipt_forecast_id', 'user_id'], 'receipt_link_operations_forecast_owner_fk')
                ->references(['id', 'user_id'])->on('receipt_forecasts')->cascadeOnDelete();
            $table->foreign(['ledger_entry_id', 'user_id'], 'receipt_link_operations_entry_owner_fk')
                ->references(['id', 'user_id'])->on('ledger_entries')->cascadeOnDelete();
            $table->unique(['user_id', 'operation_id']);
        });

        DB::table('receipt_forecast_link_operations')->insertUsing(
            ['user_id', 'receipt_forecast_link_id', 'receipt_forecast_id', 'ledger_entry_id', 'operation_id', 'kind', 'created_at', 'updated_at'],
            DB::table('receipt_forecast_links')->select([
                'user_id', 'id', 'receipt_forecast_id', 'ledger_entry_id', 'operation_id', DB::raw("'linked'"), 'created_at', 'updated_at',
            ]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_forecast_link_operations');
        Schema::table('receipt_forecast_links', function (Blueprint $table) {
            $table->dropUnique('receipt_forecast_links_owner_unique');
        });
    }
};
