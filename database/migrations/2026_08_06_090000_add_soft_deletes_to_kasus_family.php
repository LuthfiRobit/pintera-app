<?php
// database/migrations/2026_08_06_090000_add_soft_deletes_to_kasus_family.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['kasus', 'kasus_sesi', 'kasus_tugas', 'kasus_tugas_submission', 'kasus_evaluasi', 'kasus_consent'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['kasus', 'kasus_sesi', 'kasus_tugas', 'kasus_tugas_submission', 'kasus_evaluasi', 'kasus_consent'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
