<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->foreignId('siswa_id')->nullable()->after('wallet_id')->constrained('siswa')->nullOnDelete();
        });

        // Add 'menunggu_pembayaran' without dropping existing values
        DB::statement("ALTER TABLE pembayaran MODIFY status ENUM('menunggu_pembayaran', 'menunggu_verifikasi', 'lunas', 'ditolak') NOT NULL");

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->enum('topup_status', ['none', 'pending', 'completed', 'failed'])->default('none')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn('topup_status');
        });

        // Revert status enum to original
        DB::statement("ALTER TABLE pembayaran MODIFY status ENUM('menunggu_verifikasi', 'lunas', 'ditolak') NOT NULL");

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropForeign(['siswa_id']);
            $table->dropColumn('siswa_id');
        });
    }
};
