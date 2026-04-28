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
        Schema::create('snack_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_room_id');
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->onDelete('cascade');
            $table->string('requester_id');
            $table->string('requester_name');
            $table->string('requester_position');
            $table->string('requester_division');
            $table->string('approver_id');
            $table->string('approver_name');
            $table->string('approver_position');
            $table->string('approver_division');
            $table->string('status')->enum(['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snack_approvals');
    }
};
