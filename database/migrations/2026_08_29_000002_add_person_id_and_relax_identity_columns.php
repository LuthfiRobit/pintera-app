<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->text('nik')->nullable()->change();
            $table->string('nama')->nullable()->change();
            $table->string('jenis_kelamin')->nullable()->change();
            $table->string('tempat_lahir')->nullable()->change();
            $table->date('tanggal_lahir')->nullable()->change();
            $table->string('agama')->nullable()->change();
            $table->string('kewarganegaraan')->nullable()->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->text('nik')->nullable()->change();
            $table->string('nama')->nullable()->change();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->string('nama_lengkap')->nullable()->change();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->string('nama_lengkap')->nullable()->change();
            $table->string('jenis_kelamin')->nullable()->change();
            $table->string('tempat_lahir')->nullable()->change();
            $table->date('tanggal_lahir')->nullable()->change();
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('calon_murid', fn (Blueprint $table) => $table->dropColumn('person_id'));

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->string('nama_lengkap')->nullable(false)->change();
            $table->string('jenis_kelamin')->nullable(false)->change();
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->date('tanggal_lahir')->nullable(false)->change();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->string('nama_lengkap')->nullable(false)->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->text('nik')->nullable(false)->change();
            $table->string('nama')->nullable(false)->change();
        });

        Schema::table('guru', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->text('nik')->nullable(false)->change();
            $table->string('nama')->nullable(false)->change();
            $table->string('jenis_kelamin')->nullable(false)->change();
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->date('tanggal_lahir')->nullable(false)->change();
            $table->string('agama')->nullable(false)->change();
            $table->string('kewarganegaraan')->nullable(false)->change();
        });
    }
};
