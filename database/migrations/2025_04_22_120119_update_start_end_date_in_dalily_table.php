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
        Schema::table('dalily', function (Blueprint $table) {
            Schema::table('daily', function (Blueprint $table) {
                // Ubah tipe kolom menjadi dateTime
                $table->dateTime('start_date')->change();
                $table->dateTime('end_date')->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dalily', function (Blueprint $table) {
            Schema::table('daily', function (Blueprint $table) {
                // Rollback ke tipe date
                $table->date('start_date')->change();
                $table->date('end_date')->change();
            });
        });
    }
};
