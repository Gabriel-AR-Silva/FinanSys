<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pocket_id')->nullable();
            $table->string('name');
            $table->decimal('target_amount', 19, 2);
            $table->date('target_date');
            $table->uuid('operation_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'operation_id'], 'financial_goals_user_operation_unique');
            $table->index(['user_id', 'target_date'], 'financial_goals_user_target_date_index');
            $table->unique('pocket_id', 'financial_goals_pocket_unique');
            $table->foreign(['pocket_id', 'user_id'], 'financial_goals_pocket_user_fk')
                ->references(['id', 'user_id'])
                ->on('pockets');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
