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
        Schema::create('daily', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('task_id');
            $table->foreign('task_id')->references('task_id')->on('project_tasks')->onDelete('cascade');
            $table->string('employe_id');
            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
            $table->string('activity_name');
            $table->boolean('is_priority')->default(false);
            $table->enum('category', ['rutin','non-rutin','bulanan','tahunan','tambahan']);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('progress');
            $table->enum('status', ['in progress','review supervisor','review manager','approved','revised','cancelled'])->default('in progress');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily');
    }
};
