<?php
// database/migrations/2026_08_08_090000_add_batch_columns_to_kasus_tugas.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas', function (Blueprint $table) {
            $table->char('batch_id', 36)->nullable()->after('frekuensi');
            $table->unsignedInteger('batch_urutan')->default(1)->after('batch_id');
            $table->unsignedInteger('batch_total')->default(1)->after('batch_urutan');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'batch_urutan', 'batch_total']);
        });
    }
};
