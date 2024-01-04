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
        Schema::create('organizations', function (Blueprint $table) {
            $table->smallIncrements('organization_id');
            $table->unsignedSmallInteger('board_id');
            $table->string('organization_name', 100)->nullable(false);
            $table->timestamps();

            $table->foreign('board_id')->references('board_id')->on('board_organizations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
