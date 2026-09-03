<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->foreignId('kelas_terakhir_id')->nullable()->after('kelas_id')
                ->constrained('kelas')->nullOnDelete();
        });

        DB::statement("
            UPDATE siswa
            SET kelas_terakhir_id = kelas_id, kelas_id = NULL
            WHERE status != 'aktif' AND kelas_id IS NOT NULL
        ");

        // MySQL 8.0 melarang kolom dengan referential action (seperti ON DELETE SET NULL)
        // berada dalam CHECK constraint (Error 3823). Ubah FK kelas_id ke ON DELETE RESTRICT.
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->foreign('kelas_id')->references('id')->on('kelas')->restrictOnDelete();
        });

        DB::statement('
            ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif
                CHECK (status = \'aktif\' OR kelas_id IS NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE siswa DROP CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif');

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->foreign('kelas_id')->references('id')->on('kelas')->nullOnDelete();
            $table->dropConstrainedForeignId('kelas_terakhir_id');
        });
    }
};
