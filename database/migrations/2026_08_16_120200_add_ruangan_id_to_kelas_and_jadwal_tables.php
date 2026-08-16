<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('ruangan_id')->nullable()->after('pola_jam_id')->constrained('ruangan')->nullOnDelete();
        });

        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->foreignId('ruangan_id')->nullable()->after('jam_pelajaran_id')->constrained('ruangan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ruangan_id');
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ruangan_id');
        });
    }
};
