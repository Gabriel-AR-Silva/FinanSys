<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_financial_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->date('local_date');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('daily_financial_check_ins')->nullOnDelete();
            $table->unsignedBigInteger('daily_budget_version_id');
            $table->decimal('budget_amount', 19, 2);
            $table->decimal('eligible_spent', 19, 2);
            $table->decimal('margin', 20, 2);
            $table->string('rules_version', 64);
            $table->string('source', 16)->default('recorded');
            $table->dateTime('confirmed_at');
            $table->string('reason')->nullable();
            $table->uuid('operation_id');

            $table->unique(['user_id', 'operation_id'], 'dfci_user_operation_unique');
            $table->unique(['user_id', 'local_date', 'revision'], 'dfci_user_date_revision_unique');
            $table->index(['user_id', 'local_date', 'revision'], 'dfci_user_date_revision_index');
            $table->index(['user_id', 'confirmed_at'], 'dfci_user_confirmed_index');
            $table->foreign(['daily_budget_version_id', 'user_id'], 'dfci_budget_user_fk')
                ->references(['id', 'user_id'])
                ->on('daily_budget_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_financial_check_ins');
    }
};
