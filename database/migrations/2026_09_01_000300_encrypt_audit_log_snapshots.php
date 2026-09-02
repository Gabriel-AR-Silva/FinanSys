<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->longText('before_ciphertext')->nullable();
            $table->longText('after_ciphertext')->nullable();
        });

        DB::table('audit_logs')->orderBy('id')->each(function (object $audit): void {
            DB::table('audit_logs')->where('id', $audit->id)->update([
                'before_ciphertext' => $audit->before === null ? null : Crypt::encryptString($audit->before),
                'after_ciphertext' => $audit->after === null ? null : Crypt::encryptString($audit->after),
            ]);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['before', 'after']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('before_ciphertext', 'before');
            $table->renameColumn('after_ciphertext', 'after');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->json('before_plaintext')->nullable();
            $table->json('after_plaintext')->nullable();
        });

        DB::table('audit_logs')->orderBy('id')->each(function (object $audit): void {
            DB::table('audit_logs')->where('id', $audit->id)->update([
                'before_plaintext' => $audit->before === null ? null : Crypt::decryptString($audit->before),
                'after_plaintext' => $audit->after === null ? null : Crypt::decryptString($audit->after),
            ]);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['before', 'after']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('before_plaintext', 'before');
            $table->renameColumn('after_plaintext', 'after');
        });
    }
};
