<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->uuid('operation_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deletion_batch_id')->nullable();
            $table->index(['user_id', 'status']);
            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'operation_id']);
        });

        Schema::create('pockets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deletion_batch_id')->nullable();
            $table->index(['user_id', 'account_id']);
            $table->foreign(['account_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('accounts')
                ->cascadeOnDelete();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 32);
            $table->unsignedBigInteger('reference_id');
            $table->string('type', 32);
            $table->decimal('amount', 19, 2);
            $table->uuid('operation_id');
            $table->timestamp('occurred_at');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deletion_batch_id')->nullable();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['user_id', 'operation_id', 'type', 'reference_type', 'reference_id'], 'ledger_entries_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('pockets');
        Schema::dropIfExists('accounts');
    }
};
