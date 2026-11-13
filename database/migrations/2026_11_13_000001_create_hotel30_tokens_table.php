<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tautan persetujuan klaim hotel 30% untuk pelaksana SPPD yang bukan karyawan (tanpa akun)
    public function up(): void
    {
        Schema::connection('esign')->create('hotel30_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('id_doc');
            $table->unsignedBigInteger('id_step');
            $table->unsignedBigInteger('id_tujuan');
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamp('dipakai_at')->nullable();
            $table->string('hasil', 20)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('dibuat_oleh', 50)->nullable();
            $table->timestamps();
            $table->index(['id_doc', 'id_tujuan']);
        });
    }

    public function down(): void
    {
        Schema::connection('esign')->dropIfExists('hotel30_tokens');
    }
};
