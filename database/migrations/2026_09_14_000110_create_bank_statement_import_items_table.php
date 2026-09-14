<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_index');
            $table->string('bank_type', 32);
            $table->dateTimeTz('occurred_at');
            $table->decimal('amount', 18, 2);
            $table->string('direction', 12);
            $table->text('description');
            $table->char('external_id_hash', 64)->nullable();
            $table->char('fingerprint', 64);
            $table->char('dedup_key', 64)->nullable()->unique();
            $table->string('classification', 40)->default('needs_review');
            $table->string('review_status', 32)->default('pending_review');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('planning_type', 32)->nullable();
            $table->string('domain_type', 80)->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->timestamps();

            $table->unique(['bank_statement_import_id', 'source_index'], 'bank_import_item_source_unique');
            $table->index(['user_id', 'account_id', 'review_status'], 'bank_import_item_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_import_items');
    }
};
