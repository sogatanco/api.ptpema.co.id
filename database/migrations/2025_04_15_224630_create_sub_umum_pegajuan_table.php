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
        Schema::connection('umumPengajuan')->create('sub_umum_pegajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('umum_pengajuan')->onDelete('cascade');
            $table->string('nama_item');
            $table->integer('jumlah');
            $table->string('satuan');
            $table->integer('biaya_satuan');
            $table->integer('total_biaya');
            $table->text('keterangan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_umum_pegajuan');
    }
};
