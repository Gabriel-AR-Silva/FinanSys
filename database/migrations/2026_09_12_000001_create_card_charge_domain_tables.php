<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('card_charges')) {
            Schema::create('card_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('credit_card_id');
                $table->foreignId('category_id')->constrained()->restrictOnDelete();
                $table->string('type', 24);
                $table->string('description');
                $table->string('planning_type', 24);
                $table->decimal('amount', 19, 2);
                $table->decimal('paid_amount', 19, 2)->default(0);
                $table->date('charged_on');
                $table->date('due_on');
                $table->string('status', 16)->default('pending');
                $table->uuid('operation_id');
                $table->timestamps();
                $table->foreign(['credit_card_id', 'user_id'])->references(['id', 'user_id'])->on('credit_cards')->restrictOnDelete();
                $table->unique(['id', 'user_id']);
                $table->unique(['user_id', 'operation_id']);
                $table->index(['user_id', 'due_on', 'status']);
            });
        }

        if (! Schema::hasColumn('card_payments', 'selected_charge_ids')) {
            Schema::table('card_payments', function (Blueprint $table) {
                $table->json('selected_charge_ids')->nullable()->after('paid_on');
            });
        }

        if (! Schema::hasTable('card_charge_payment_allocations')) {
            Schema::create('card_charge_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('card_payment_id');
                $table->unsignedBigInteger('card_charge_id');
                $table->decimal('amount', 19, 2);
                $table->timestamps();
                $table->foreign(['card_payment_id', 'user_id'])->references(['id', 'user_id'])->on('card_payments')->cascadeOnDelete();
                $table->foreign(['card_charge_id', 'user_id'])->references(['id', 'user_id'])->on('card_charges')->restrictOnDelete();
                $table->unique(
                    ['card_payment_id', 'card_charge_id'],
                    'card_payment_charge_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_charge_payment_allocations');
        Schema::table('card_payments', function (Blueprint $table) {
            $table->dropColumn('selected_charge_ids');
        });
        Schema::dropIfExists('card_charges');
    }
};
