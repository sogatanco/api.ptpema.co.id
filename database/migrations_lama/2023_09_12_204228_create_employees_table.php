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
        Schema::create('employees', function (Blueprint $table) {
            $table->string("employe_id", 20)->nullable(false)->primary();
            $table->unsignedBigInteger('user_id')->nullable(false);
            $table->unsignedSmallInteger('position_id')->nullable(false);
            $table->string('first_name', 100)->nullable(false);
            $table->string('last_name', 100)->nullable(false);
            $table->string('gender', 10)->nullable(false);
            $table->string('religion', 50)->nullable(false);
            $table->date('birthday')->nullable(false);
            $table->string('birthday_place', 100)->nullable(false);
            $table->string('marital_status', 50)->nullable(false);
            $table->string('img', 100)->nullable();
            $table->boolean('employe_active')->nullable()->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('position_id')->references('position_id')->on('positions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
