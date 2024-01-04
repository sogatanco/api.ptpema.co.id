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
        Schema::create('activity_levels', function (Blueprint $table) {
            $table->smallIncrements('level_id');
            $table->string('level_desc', 100)->nullable(false);
            $table->string('employe_id')->nullable(false);
            $table->timestamps();

            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_levels');
    }
};
