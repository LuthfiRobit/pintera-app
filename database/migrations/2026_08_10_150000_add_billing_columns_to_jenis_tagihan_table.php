<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->unsignedInteger('priority_score')->nullable()->after('kategori');
            $table->decimal('default_amount', 12, 2)->nullable()->after('priority_score');
            $table->enum('mode', ['manual', 'otomatis'])->default('manual')->after('default_amount');
            $table->date('tanggal_mulai')->nullable()->after('mode');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
            $table->unsignedTinyInteger('tanggal_generate')->nullable()->after('tanggal_selesai');
            $table->unsignedTinyInteger('hari_jatuh_tempo')->nullable()->after('tanggal_generate');
            $table->unsignedInteger('va_expire_hours')->nullable()->after('hari_jatuh_tempo');
            $table->boolean('is_active')->default(true)->after('va_expire_hours');
            $table->string('last_generated_period', 7)->nullable()->after('is_active');
        });

        DB::statement("ALTER TABLE jenis_tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'lainnya', 'spp', 'tahunan', 'kegiatan', 'custom') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE jenis_tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'lainnya') NOT NULL");

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->dropColumn([
                'priority_score', 'default_amount', 'mode', 'tanggal_mulai', 'tanggal_selesai',
                'tanggal_generate', 'hari_jatuh_tempo', 'va_expire_hours', 'is_active', 'last_generated_period',
            ]);
        });
    }
};
