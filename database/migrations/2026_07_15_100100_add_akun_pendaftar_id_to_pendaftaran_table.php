<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->foreignId('akun_pendaftar_id')->nullable()->after('sk_ppdb_id')
                ->constrained('akun_pendaftar')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_pendaftar_id');
        });
    }
};
