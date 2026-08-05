<?php
// database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('orang_tua_id');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
};
