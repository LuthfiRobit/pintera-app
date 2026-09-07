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
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable()->change();
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable()->change();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable(false)->change();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable(false)->change();
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });
    }
};
