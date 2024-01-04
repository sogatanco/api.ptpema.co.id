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
        Schema::create('activity_bases', function (Blueprint $table) {
            $table->integerIncrements("base_id");
            $table->text('base_description')->nullable(false);
            $table->year('base_year')->nullable(false);
            $table->boolean('active')->default(true);
            $table->string('employe_id')->nullable();
            $table->timestamps();

            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_bases');
    }
};
