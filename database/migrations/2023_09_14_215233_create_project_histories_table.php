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
        Schema::create('project_histories', function (Blueprint $table) {
            $table->bigIncrements("history_id");
            $table->unsignedBigInteger("project_id")->nullable(false);
            $table->string("employe_id", 20)->nullable(false);
            $table->text("history_desc")->nullable(false);
            $table->boolean("active")->default(true);
            $table->timestamps();

            $table->foreign('project_id')->on('projects')->references('project_id')->onDelete('cascade');
            $table->foreign('employe_id')->on('employees')->references('employe_id')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_histories');
    }
};
