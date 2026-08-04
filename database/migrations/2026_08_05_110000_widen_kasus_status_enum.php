<?php
// database/migrations/2026_08_05_110000_widen_kasus_status_enum.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE kasus MODIFY status ENUM('diajukan', 'menunggu_consent', 'ditugaskan', 'berjalan', 'eskalasi', 'selesai') NOT NULL DEFAULT 'diajukan'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE kasus MODIFY status ENUM('diajukan', 'menunggu_consent', 'ditugaskan') NOT NULL DEFAULT 'diajukan'");
    }
};
