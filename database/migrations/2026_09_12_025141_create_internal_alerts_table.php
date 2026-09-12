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
        Schema::create('internal_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('alert_date');
            $table->string('view', 16);
            $table->foreignId('financial_evaluation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_situation', 32);
            $table->string('worst_situation', 32);
            $table->decimal('current_deficit', 19, 2)->nullable();
            $table->boolean('deficit_seen')->default(false);
            $table->timestamp('recovered_at')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['user_id', 'alert_date', 'view'], 'internal_alerts_identity');
            $table->index(['user_id', 'alert_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_alerts');
    }
};
