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
        Schema::connection('mysql4')->create('biaya_sppds', function (Blueprint $table) {
            $table->id();
            $table->integer('id_golongan');
            $table->integer('id_category');
            $table->integer('tiket');
            $table->integer('um');
            $table->integer('us');
            $table->integer('tr');
            $table->integer('hotel');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biaya_sppds');
    }
};
