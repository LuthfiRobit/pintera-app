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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('va_number')->nullable()->unique();
            $table->decimal('total_topup', 15, 2)->default(0);
            $table->decimal('total_deducted', 15, 2)->default(0);
            $table->timestamps();

            $table->unique('siswa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
