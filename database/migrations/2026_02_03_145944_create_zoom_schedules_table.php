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
        Schema::create('zoom_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employe_id');
            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
            $table->string('meeting_id')->unique();
            $table->string('topic');
            $table->string('date');
            $table->string('start_time');
            $table->string('end_time');
            $table->string('agenda')->nullable();
            $table->string('join_url');
            $table->string('password')->nullable();
            $table->string('status')->enum(['scheduled', 'started', 'ended', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoom_schedules');
    }
};
