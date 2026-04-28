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
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('employe_id');
            $table->string('employe_name');
            $table->string('division_name');
            $table->unsignedBigInteger('room_id');
            $table->foreign('room_id')->references('id')->on('meeting_rooms')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('agenda');
            $table->integer('number_of_participants');
            $table->boolean('need_zoom')->default(false);
            $table->unsignedInteger('zoom_schedule_id')->nullable();
            $table->foreign('zoom_schedule_id')->references('id')->on('zoom_schedules')->onDelete('cascade');
            $table->boolean('need_snack')->default(false);
            $table->string('snack_notes')->nullable();
            $table->string('snack_status')->enum(['not ordered', 'requested', 'confirmed'])->default('not ordered');
            $table->string('status')->enum(['booked', 'cancelled'])->default('booked');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};
