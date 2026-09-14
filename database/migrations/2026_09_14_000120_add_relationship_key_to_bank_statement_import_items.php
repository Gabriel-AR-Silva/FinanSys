<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_import_items', function (Blueprint $table): void {
            $table->char('relationship_key', 64)->nullable()->after('external_id_hash');
            $table->index(['bank_statement_import_id', 'relationship_key'], 'bank_import_item_relationship_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_import_items', function (Blueprint $table): void {
            $table->dropIndex('bank_import_item_relationship_idx');
            $table->dropColumn('relationship_key');
        });
    }
};
