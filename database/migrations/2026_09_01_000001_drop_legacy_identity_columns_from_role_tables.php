<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn([
                'user_id', 'nik', 'nik_hash', 'nama', 'jenis_kelamin', 'tempat_lahir',
                'tanggal_lahir', 'agama', 'kewarganegaraan', 'alamat_jalan', 'rt', 'rw',
                'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
                'no_hp', 'email',
            ]);
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nik', 'nik_hash', 'nama', 'no_hp', 'email']);
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nama_lengkap', 'nik', 'no_hp', 'email', 'alamat']);
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama']);
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->dropColumn([
                'nik', 'nik_hash', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir',
                'tanggal_lahir', 'agama', 'no_telepon', 'email_kontak',
            ]);
        });
    }

    public function down(): void
    {
        // Ireversibel secara data (kolom yang di-drop tidak bisa dikembalikan isinya).
        // down() sengaja tidak diimplementasikan penuh -- kalau butuh rollback,
        // pulihkan dari backup/snapshot sebelum migration ini, bukan lewat down().
        throw new RuntimeException('Migration ini tidak reversibel. Restore dari backup jika perlu rollback.');
    }
};
