<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'spp', 'tahunan', 'kegiatan', 'custom', 'lainnya') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'spp', 'tahunan', 'kegiatan', 'custom') NOT NULL");
    }
};
