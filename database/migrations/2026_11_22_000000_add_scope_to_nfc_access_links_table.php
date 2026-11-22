<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfc_access_links', function (Blueprint $table) {
            $table->string('scope', 8)->default('edit');
        });
    }

    public function down(): void
    {
        Schema::table('nfc_access_links', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
