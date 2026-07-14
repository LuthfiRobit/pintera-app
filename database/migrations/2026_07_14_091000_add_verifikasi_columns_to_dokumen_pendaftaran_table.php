<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_pendaftaran', function (Blueprint $table) {
            $table->text('catatan_verifikasi')->nullable()->after('status_verifikasi');
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->after('catatan_verifikasi')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diverifikasi_oleh_user_id');
            $table->dropColumn(['catatan_verifikasi', 'diverifikasi_pada']);
        });
    }
};
