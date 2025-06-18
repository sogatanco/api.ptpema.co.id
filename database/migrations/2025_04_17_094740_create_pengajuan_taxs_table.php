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
        Schema::connection('umumPengajuan')->create('pengajuan_taxs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_pengajuan_id')->constrained('sub_umum_pegajuan')->onDelete('cascade');
            $table->string('nama_pajak');
            $table->integer('persentase');
            // $table->text('keterangan');
            $table->enum('calculation', ['increase', 'decrease']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_taxs');
    }
};
