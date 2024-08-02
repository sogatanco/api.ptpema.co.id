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
        Schema::connection('mysql4')->create('realisasis', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('id_tujuan');
            $table->integer('rill_tiket');
            $table->integer('rill_hotel');
            $table->string('boarding_pass');
            $table->string('laporan');
            $table->string('surat_pernyataan');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realisasis');
    }
};
