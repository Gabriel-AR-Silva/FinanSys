<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table): void {
            $table->decimal('credit_limit', 19, 2)->nullable()->after('due_day');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table): void {
            $table->dropColumn('credit_limit');
        });
    }
};
