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
        // 1. Rename kolom dulu
        Schema::table('daily_attachments', function (Blueprint $table) {
            $table->renameColumn('file_name', 'original_name');
        });

        // 2. Tambah kolom baru setelah itu
        Schema::table('daily_attachments', function (Blueprint $table) {
            $table->string('file_path')->after('daily_id');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->integer('size')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_attachments', function (Blueprint $table) {
            $table->renameColumn('original_name', 'file_name');
            $table->dropColumn(['file_path', 'mime_type', 'size']);
        });
    }
};
