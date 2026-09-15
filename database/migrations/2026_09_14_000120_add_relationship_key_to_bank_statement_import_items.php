<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bank_statement_import_items', 'relationship_key')) {
            Schema::table('bank_statement_import_items', function (Blueprint $table): void {
                $table->char('relationship_key', 64)->nullable()->after('external_id_hash');
            });
        }

        if (! Schema::hasIndex('bank_statement_import_items', 'bank_import_item_relationship_idx')) {
            Schema::table('bank_statement_import_items', function (Blueprint $table): void {
                $table->index(['bank_statement_import_id', 'relationship_key'], 'bank_import_item_relationship_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('bank_statement_import_items', 'bank_import_item_relationship_idx')) {
            Schema::table('bank_statement_import_items', function (Blueprint $table): void {
                $table->dropIndex('bank_import_item_relationship_idx');
            });
        }

        if (Schema::hasColumn('bank_statement_import_items', 'relationship_key')) {
            Schema::table('bank_statement_import_items', function (Blueprint $table): void {
                $table->dropColumn('relationship_key');
            });
        }
    }
};
