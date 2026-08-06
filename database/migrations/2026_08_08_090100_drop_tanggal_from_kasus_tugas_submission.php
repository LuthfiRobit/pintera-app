<?php
// database/migrations/2026_08_08_090100_drop_tanggal_from_kasus_tugas_submission.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('orang_tua_id');
        });
    }
};
