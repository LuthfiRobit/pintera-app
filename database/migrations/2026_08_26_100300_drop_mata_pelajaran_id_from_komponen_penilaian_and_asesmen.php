<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn(['mata_pelajaran_id', 'elemen_cp']);
            $table->string('subjek_type')->nullable(false)->change();
            $table->unsignedBigInteger('subjek_id')->nullable(false)->change();
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn('mata_pelajaran_id');
            $table->string('subjek_type')->nullable(false)->change();
            $table->unsignedBigInteger('subjek_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->string('elemen_cp', 30)->nullable();
            $table->string('subjek_type')->nullable()->change();
            $table->unsignedBigInteger('subjek_id')->nullable()->change();
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->string('subjek_type')->nullable()->change();
            $table->unsignedBigInteger('subjek_id')->nullable()->change();
        });
    }
};
