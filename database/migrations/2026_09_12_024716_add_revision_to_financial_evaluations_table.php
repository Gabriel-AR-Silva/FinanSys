<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REVISION_IDENTITY = 'financial_evaluations_revision_identity';

    private const ORIGINAL_IDENTITY = 'financial_evaluations_identity';

    private const LOOKUP_INDEX = 'fin_eval_user_date_view_rules_idx';

    private function hasIndex(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM `financial_evaluations`'))
            ->contains(fn (object $index): bool => $index->Key_name === $name);
    }

    private function hasSupersedesForeignKey(): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'financial_evaluations')
            ->where('COLUMN_NAME', 'supersedes_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function up(): void
    {
        // MySQL DDL is not transactional: a failed migration may already have added columns or indexes.
        if (! Schema::hasColumn('financial_evaluations', 'revision')) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->unsignedInteger('revision')->default(1)->after('rules_version');
            });
        }

        if (! Schema::hasColumn('financial_evaluations', 'supersedes_id')) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->foreignId('supersedes_id')->nullable()->after('revision');
            });
        }

        if (! $this->hasSupersedesForeignKey()) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->foreign('supersedes_id')->references('id')->on('financial_evaluations')->nullOnDelete();
            });
        }

        if ($this->hasIndex(self::ORIGINAL_IDENTITY)) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->dropUnique(self::ORIGINAL_IDENTITY);
            });
        }

        if (! $this->hasIndex(self::REVISION_IDENTITY)) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->unique(['user_id', 'evaluation_date', 'view', 'rules_version', 'revision'], self::REVISION_IDENTITY);
            });
        }

        if (! $this->hasIndex(self::LOOKUP_INDEX)) {
            Schema::table('financial_evaluations', function (Blueprint $table): void {
                $table->index(['user_id', 'evaluation_date', 'view', 'rules_version'], self::LOOKUP_INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::table('financial_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['supersedes_id']);
            $table->dropUnique(self::REVISION_IDENTITY);
            $table->dropIndex(self::LOOKUP_INDEX);
            $table->unique(['user_id', 'evaluation_date', 'view', 'rules_version'], self::ORIGINAL_IDENTITY);
            $table->dropColumn(['revision', 'supersedes_id']);
        });
    }
};
