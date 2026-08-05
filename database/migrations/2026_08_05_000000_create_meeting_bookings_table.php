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
        Schema::create('meeting_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->integer('participants')->default(0);
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('zoom_required')->default(false);
            $table->boolean('consumption_required')->default(false);
            $table->text('consumption_detail')->nullable();
            $table->string('room');
            $table->string('zoom_id')->nullable();
            $table->string('zoom_link')->nullable();
            $table->string('zoom_password')->nullable();
            $table->string('created_by');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index('zoom_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_bookings');
    }
};
