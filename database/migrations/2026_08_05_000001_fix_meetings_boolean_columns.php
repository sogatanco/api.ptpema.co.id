<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `meetings` MODIFY `zoom_required` TINYINT(1) NOT NULL DEFAULT 0, MODIFY `consumption_required` TINYINT(1) NOT NULL DEFAULT 0, MODIFY `zoom_id` BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `meetings` MODIFY `zoom_required` TINYINT(1) NOT NULL DEFAULT 0, MODIFY `consumption_required` TINYINT(1) NOT NULL DEFAULT 0, MODIFY `zoom_id` BIGINT UNSIGNED NULL');
    }
};
