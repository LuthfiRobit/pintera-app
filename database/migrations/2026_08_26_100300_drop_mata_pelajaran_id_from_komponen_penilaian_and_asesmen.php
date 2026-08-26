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
            $table->dropColumn('mata_pelajaran_id');
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn('mata_pelajaran_id');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->cascadeOnDelete();
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->cascadeOnDelete();
        });
    }
};
