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
        Schema::table('financial_evaluations', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1)->after('rules_version');
            $table->foreignId('supersedes_id')->nullable()->after('revision')->constrained('financial_evaluations')->nullOnDelete();
            $table->dropUnique('financial_evaluations_identity');
            $table->unique(['user_id', 'evaluation_date', 'view', 'rules_version', 'revision'], 'financial_evaluations_revision_identity');
            $table->index(['user_id', 'evaluation_date', 'view', 'rules_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_evaluations', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropUnique('financial_evaluations_revision_identity');
            $table->dropIndex('financial_evaluations_user_id_evaluation_date_view_rules_version_index');
            $table->unique(['user_id', 'evaluation_date', 'view', 'rules_version'], 'financial_evaluations_identity');
            $table->dropColumn(['revision', 'supersedes_id']);
        });
    }
};
