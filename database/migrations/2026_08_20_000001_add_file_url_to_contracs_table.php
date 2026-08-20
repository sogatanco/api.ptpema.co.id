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
        Schema::table('contracs', function (Blueprint $table) {
            if (!Schema::hasColumn('contracs', 'file_url')) {
                $table->string('file_url')->nullable()->after('pic');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracs', function (Blueprint $table) {
            if (Schema::hasColumn('contracs', 'file_url')) {
                $table->dropColumn('file_url');
            }
        });
    }
};
