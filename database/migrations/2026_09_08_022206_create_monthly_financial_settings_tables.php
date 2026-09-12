<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_financial_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);
            $table->string('protection_type', 16)->default('fixed');
            $table->decimal('protection_value', 19, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'month']);
            $table->unique(['id', 'user_id']);
        });
        Schema::create('essential_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_financial_setting_id');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign(['monthly_financial_setting_id', 'user_id'], 'essential_budget_owner_fk')
                ->references(['id', 'user_id'])->on('monthly_financial_settings')->cascadeOnDelete();
            $table->unique(['monthly_financial_setting_id', 'category_id'], 'essential_budget_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essential_budgets');
        Schema::dropIfExists('monthly_financial_settings');
    }
};
