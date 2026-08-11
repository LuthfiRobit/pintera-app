<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bri_virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->nullable()->constrained('pembayaran')->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->cascadeOnDelete();
            $table->enum('va_type', ['WALLET_PERMANENT', 'BILL_DIRECT']);
            $table->string('va_number')->unique();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->enum('status', ['PERMANENT', 'WAITING', 'PAID', 'EXPIRED']);
            $table->json('callback_payload')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'va_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_virtual_accounts');
    }
};
