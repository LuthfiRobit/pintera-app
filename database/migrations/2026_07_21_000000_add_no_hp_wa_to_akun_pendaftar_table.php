<?php
// database/migrations/2026_07_21_000000_add_no_hp_wa_to_akun_pendaftar_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akun_pendaftar', function (Blueprint $table) {
            $table->string('no_hp_wa')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('akun_pendaftar', function (Blueprint $table) {
            $table->dropColumn('no_hp_wa');
        });
    }
};
