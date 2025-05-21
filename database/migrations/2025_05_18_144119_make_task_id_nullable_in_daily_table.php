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
        Schema::table('daily', function (Blueprint $table) {
            // Drop foreign key constraint terlebih dahulu
            $table->dropForeign(['task_id']);

            // Ubah kolom jadi nullable
            $table->unsignedInteger('task_id')->nullable()->change();

            // Tambahkan kembali foreign key constraint
            $table->foreign('task_id')->references('task_id')->on('project_tasks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily', function (Blueprint $table) {
            // Drop foreign key terlebih dahulu
            $table->dropForeign(['task_id']);

            // Kembalikan ke non-nullable
            $table->unsignedInteger('task_id')->nullable(false)->change();

            // Tambahkan kembali foreign key
            $table->foreign('task_id')->references('task_id')->on('project_tasks')->onDelete('cascade');
        });
    }
};
