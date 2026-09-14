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
        Schema::create('financial_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('evaluation_date');
            $table->string('view', 16);
            $table->string('rules_version', 32);
            $table->string('source', 16)->default('recorded');
            $table->timestamp('evaluated_at');
            $table->json('result');
            $table->timestamps();
            $table->unique(['user_id', 'evaluation_date', 'view', 'rules_version'], 'financial_evaluations_identity');
            $table->index(['user_id', 'evaluation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_evaluations');
    }
};
