<?php
// database/migrations/2026_08_22_090400_create_employee_qr_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->morphs('pegawai');
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qr_codes');
    }
};
