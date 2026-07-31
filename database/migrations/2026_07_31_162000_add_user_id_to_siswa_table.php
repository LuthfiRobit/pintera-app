<?php
// database/migrations/2026_07_31_162000_add_user_id_to_siswa_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            // nullOnDelete (bukan cascadeOnDelete seperti guru.user_id) dengan
            // sengaja: menghapus akun login siswa tidak boleh ikut menghapus
            // riwayat akademiknya (nilai, presensi). Kalau User-nya dihapus,
            // siswa cukup kehilangan akses login sampai akun dibuat ulang.
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
