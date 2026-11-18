<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql4')->table('proses', function (Blueprint $table) {
            // tidak_ada | pelaksana | admin | umum
            $table->string('sumber_tiket', 20)->nullable();
        });

        Schema::connection('mysql4')->create('proses_tiket', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sppd')->index();
            $table->unsignedBigInteger('id_tujuan')->index();
            $table->string('arah', 10); // pergi | pulang
            $table->string('keterangan')->nullable();
            $table->string('file');
            $table->decimal('harga', 15, 2)->default(0);
            $table->string('uploaded_by')->nullable(); // employe_id berbentuk teks, mis. 202305031P
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql4')->dropIfExists('proses_tiket');
        Schema::connection('mysql4')->table('proses', function (Blueprint $table) {
            $table->dropColumn('sumber_tiket');
        });
    }
};
