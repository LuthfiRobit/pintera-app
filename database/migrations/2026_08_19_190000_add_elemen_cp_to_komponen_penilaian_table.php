<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->string('elemen_cp', 30)->nullable()->after('kktp_minimal');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropColumn('elemen_cp');
        });
    }
};
