<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->uuid('record_key');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_records');
    }
};
