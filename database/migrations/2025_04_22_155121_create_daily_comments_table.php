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
        Schema::create('daily_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('daily_id');
            $table->foreign('daily_id')->references('id')->on('daily')->onDelete('cascade');
            $table->string('employe_id');
            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('reply_id')->nullable();
            $table->foreign('reply_id')->references('id')->on('daily_comments')->onDelete('cascade');
            $table->text('comment');
            $table->string('attachment_file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_comments');
    }
};
