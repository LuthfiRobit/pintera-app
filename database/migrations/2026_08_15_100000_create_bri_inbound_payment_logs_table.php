<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bri_inbound_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('payment_request_id')->unique();
            $table->string('va_number');
            $table->decimal('amount', 15, 2);
            $table->foreignId('pembayaran_id')->nullable()->constrained('pembayaran')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_inbound_payment_logs');
    }
};
