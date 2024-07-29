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
        Schema::create('positions', function (Blueprint $table) {
            $table->smallIncrements('position_id');
            $table->unsignedSmallInteger('organization_id')->nullable(false);
            $table->string('position_name', 50)->nullable(false);
            $table->timestamps();

            $table->foreign('organization_id')->references('organization_id')->on('organizations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
