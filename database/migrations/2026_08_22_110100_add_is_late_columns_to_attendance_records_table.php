<?php
// database/migrations/2026_08_22_110100_add_is_late_columns_to_attendance_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('is_late')->default(false)->after('status');
            $table->unsignedInteger('late_minutes')->nullable()->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['is_late', 'late_minutes']);
        });
    }
};
