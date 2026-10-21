<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nullable, so existing rows stay untouched
    public function up(): void
    {
        Schema::connection('esign')->table('verif_steps', function (Blueprint $table) {
            $table->char('chain_sha256', 64)->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::connection('esign')->table('verif_steps', function (Blueprint $table) {
            $table->dropColumn('chain_sha256');
        });
    }
};
