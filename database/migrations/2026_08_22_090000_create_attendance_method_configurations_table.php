<?php
// database/migrations/2026_08_22_090000_create_attendance_method_configurations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_method_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('method');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'method'], 'attendance_method_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_method_configurations');
    }
};
