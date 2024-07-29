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
        Schema::create('business_plans', function (Blueprint $table) {
            $table->bigIncrements('business_id');
            $table->string('employe_id')->nullable();
            $table->string('business_number', 30)->nullable(false);
            $table->string('business_type', 100)->nullable(false);
            $table->text('business_desc')->nullable(false);
            $table->float('investment')->nullable(false);
            $table->float('df')->nullable(false);
            $table->float('irr')->nullable(false);
            $table->float('npv')->nullable(false);
            $table->float('pi')->nullable(false);
            $table->float('pbp')->nullable(false);
            $table->text('conclusion')->nullable(false);
            $table->timestamps();

            $table->foreign('employe_id')->references('employe_id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_plans');
    }
};
