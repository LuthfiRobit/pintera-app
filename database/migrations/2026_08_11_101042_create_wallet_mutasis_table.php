<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_mutasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('pembayaran_id')->nullable()->constrained('pembayaran')->nullOnDelete();
            $table->enum('tipe', ['topup', 'debit', 'refund']);
            $table->decimal('amount', 15, 2);
            $table->decimal('saldo_sebelum', 15, 2);
            $table->decimal('saldo_sesudah', 15, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_mutasi');
    }
};
