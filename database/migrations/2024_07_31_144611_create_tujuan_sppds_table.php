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
        Schema::connection('mysql4')->create('tujuan_sppds', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('id_sppd');
            $table->smallInteger('jenis_sppd');
            $table->smallInteger('dasar');
            $table->smallInteger('klasifikasi');
            $table->smallInteger('sumber');
            $table->integer('p_tiket');
            $table->integer('p_um');
            $table->integer('p_tl');
            $table->integer('p_us');
            $table->integer('p_hotel');
            $table->smallInteger('kategori');
            $table->string('detail_tujuan');
            $table->string('tugas');
            $table->dateTime('waktu_berangkat');
            $table->dateTime('waktu_kembali');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tujuan_sppds');
    }
};
