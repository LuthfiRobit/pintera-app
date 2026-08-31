<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->unique(['person_id', 'lembaga_id'], 'uq_guru_person_lembaga');
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->unique('person_id', 'uq_orang_tua_person');
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropUnique('uq_guru_person_lembaga');
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropUnique('uq_orang_tua_person');
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });
    }
};
