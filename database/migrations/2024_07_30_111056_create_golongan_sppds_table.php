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
        Schema::connection('mysql4')->create('golongan_sppds', function (Blueprint $table) {
            $table->id();
            $table->string('golongan');
            $table->integer('id_pihak');
            $table->string('paraf');
            $table->string('sign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('golongan_sppds');
    }
};
