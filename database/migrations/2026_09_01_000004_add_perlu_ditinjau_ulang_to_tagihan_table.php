<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->boolean('perlu_ditinjau_ulang')->default(false)->after('discount_type');
            $table->text('alasan_perlu_ditinjau')->nullable()->after('perlu_ditinjau_ulang');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn(['perlu_ditinjau_ulang', 'alasan_perlu_ditinjau']);
        });
    }
};
