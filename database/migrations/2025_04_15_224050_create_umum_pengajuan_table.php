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
        Schema::connection('umumPengajuan')->create('umum_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->string('pengajuan');
            $table->string('no_dokumen');
            $table->string('lampiran');
            $table->string('created_by');
            $table->string('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('umum_pengajuan');
    }
};
