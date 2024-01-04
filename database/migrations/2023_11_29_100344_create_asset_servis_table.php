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
        Schema::create('asset_servis', function (Blueprint $table) {
            $table->id();
            $table->integer('asset_child');
            $table->string('request_by');
            $table->string('complaint');
            $table->string('status');
            $table->integer('cost');
            $table->string('proof');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_servis');
    }
};
