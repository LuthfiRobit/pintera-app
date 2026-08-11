<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bri_qris_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->constrained('pembayaran')->cascadeOnDelete();
            $table->enum('qris_type', ['DIRECT']);
            $table->decimal('amount', 15, 2);
            $table->text('qr_code');
            $table->timestamp('expired_at');
            $table->enum('status', ['WAITING', 'PAID', 'EXPIRED']);
            $table->json('callback_payload')->nullable();
            $table->timestamps();

            $table->index(['pembayaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_qris_payments');
    }
};
