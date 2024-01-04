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
        Schema::create('projects', function (Blueprint $table) {
            $table->bigIncrements("project_id", 20);
            $table->string("project_number", 20)->nullable(false)->unique();
            $table->unsignedSmallInteger("division")->nullable(false);
            $table->string("project_name", 255)->nullable(false);
            $table->date("start_date")->nullable(false);
            $table->date("end_date")->nullable(false);
            $table->longText("goals")->nullable(false);
            $table->float("estimated_income", 9,0)->nullable(false);
            $table->float("estimated_cost", 9,0)->nullable(false);
            $table->unsignedInteger("base_id")->nullable(false);
            $table->unsignedSmallInteger("level_id")->nullable(false);
            $table->unsignedBigInteger("business_id")->nullable(false);
            $table->string("created_by", 20)->nullable(false);
            $table->timestamps();

            $table->foreign('created_by')->references('employe_id')->on('employees')->onDelete('cascade');
            $table->foreign('division')->references('organization_id')->on('organizations')->onDelete('cascade');
            $table->foreign('base_id')->references('base_id')->on('activity_bases')->onDelete('cascade');
            $table->foreign('level_id')->references('level_id')->on('activity_levels')->onDelete('cascade');
            $table->foreign('business_id')->references('business_id')->on('business_plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
