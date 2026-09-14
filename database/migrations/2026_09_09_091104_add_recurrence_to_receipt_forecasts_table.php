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
            $table->uuid('series_id')->nullable()->after('operation_id');
            $table->unsignedSmallInteger('series_position')->nullable()->after('series_id');
            $table->unsignedTinyInteger('original_day')->nullable()->after('series_position');
            $table->unique(['user_id', 'series_id', 'series_position'], 'receipt_forecasts_series_position_unique');
            $table->index(['user_id', 'series_id', 'expected_on'], 'receipt_forecasts_series_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_forecasts', function (Blueprint $table) {
            $table->dropUnique('receipt_forecasts_series_position_unique');
            $table->dropIndex('receipt_forecasts_series_date_index');
            $table->dropColumn(['series_id', 'series_position', 'original_day']);
        });
    }
};
