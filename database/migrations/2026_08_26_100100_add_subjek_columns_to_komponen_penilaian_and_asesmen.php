<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->string('subjek_type')->nullable()->after('lembaga_id');
            $table->unsignedBigInteger('subjek_id')->nullable()->after('subjek_type');
            $table->index(['subjek_type', 'subjek_id'], 'idx_komp_subjek');
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->string('subjek_type')->nullable()->after('lembaga_id');
            $table->unsignedBigInteger('subjek_id')->nullable()->after('subjek_type');
            $table->index(['subjek_type', 'subjek_id'], 'idx_asesmen_subjek');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropIndex('idx_komp_subjek');
            $table->dropColumn(['subjek_type', 'subjek_id']);
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->dropIndex('idx_asesmen_subjek');
            $table->dropColumn(['subjek_type', 'subjek_id']);
        });
    }
};
