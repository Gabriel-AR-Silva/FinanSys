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
        Schema::table('pockets', function (Blueprint $table) {
            $table->uuid('operation_id')->nullable()->after('name');
            $table->unique(['user_id', 'operation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pockets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'operation_id']);
            $table->dropColumn('operation_id');
        });
    }
};
