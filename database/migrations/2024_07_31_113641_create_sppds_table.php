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
        Schema::connection('mysql4')->create('sppds', function (Blueprint $table) {
            $table->id();
            $table->string('id_pihak');
            $table->string('nomor_sppd');
            $table->string('nomor_dokumen');
            $table->string('employe_id');
            $table->string('nama');
            $table->string('jabatan');
            $table->string('submitted_by');
            $table->string('golongan_rate');
            $table->smallInteger('ketetapan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sppds');
    }
};
