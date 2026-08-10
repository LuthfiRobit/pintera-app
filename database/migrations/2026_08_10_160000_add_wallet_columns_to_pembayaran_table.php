<?php
// database/migrations/2026_08_10_160000_add_wallet_columns_to_pembayaran_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_id')->nullable()->after('cicilan_id');
            $table->boolean('is_auto_allocation')->default(false)->after('metode');
            $table->string('channel_reference')->nullable()->after('is_auto_allocation');
            $table->enum('identifier_method', ['manual', 'nfc'])->default('manual')->after('channel_reference');

            $table->index('wallet_id');
        });

        DB::statement("ALTER TABLE pembayaran MODIFY metode ENUM('transfer_manual', 'va_bri', 'cash', 'qris', 'wallet_auto', 'wallet_saldo') NOT NULL DEFAULT 'transfer_manual'");
        DB::statement("ALTER TABLE pembayaran MODIFY sumber ENUM('calon_siswa', 'admin', 'orang_tua') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pembayaran MODIFY sumber ENUM('calon_siswa', 'admin') NOT NULL");
        DB::statement("ALTER TABLE pembayaran MODIFY metode ENUM('transfer_manual', 'va_bri') NOT NULL DEFAULT 'transfer_manual'");

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropIndex(['wallet_id']);
            $table->dropColumn(['wallet_id', 'is_auto_allocation', 'channel_reference', 'identifier_method']);
        });
    }
};
