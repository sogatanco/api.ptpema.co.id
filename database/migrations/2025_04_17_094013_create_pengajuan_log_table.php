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
        Schema::connection('umumPengajuan')->create('pengajuan_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('umum_pengajuan')->onDelete('cascade');
            $table->string('employe_id');
            $table->string('position_name');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_log');
    }
};
