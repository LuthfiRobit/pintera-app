<?php
// database/migrations/2026_08_10_180000_drop_iuran_columns_from_lembaga_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn(['memungut_iuran', 'nominal_iuran', 'periode_iuran']);
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->boolean('memungut_iuran')->default(false);
            $table->decimal('nominal_iuran', 15, 2)->nullable();
            $table->enum('periode_iuran', ['bulanan', 'tahunan'])->nullable();
        });
    }
};
