<?php
// database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $jumlahTerpakai = DB::table('mata_pelajaran')->where('tipe', 'aspek_perkembangan')->count();

        if ($jumlahTerpakai > 0) {
            throw new \RuntimeException(
                "Migration dibatalkan: ditemukan {$jumlahTerpakai} baris mata_pelajaran dengan tipe='aspek_perkembangan'. " .
                "Migration ini mengasumsikan nol baris terpakai (dikonfirmasi kosong di seluruh seeder demo saat spec ditulis). " .
                "Selesaikan migrasi data baris tersebut secara manual (pilih konsolidasi ke ElemenCp atau tipe lain) sebelum menjalankan migration ini."
            );
        }

        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel', 'aspek_perkembangan') NOT NULL");
    }
};
