<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrimonial_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 80)->nullable();
            $table->decimal('estimated_value', 19, 2);
            $table->decimal('debt_balance', 19, 2)->default(0);
            $table->date('valued_on');
            $table->timestamps();

            $table->index(['user_id', 'valued_on'], 'patrimonial_assets_user_valued_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrimonial_assets');
    }
};
