<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_pembelajaran', function (Blueprint $table) {
            $table->foreignId('diisi_oleh_guru_id')->nullable()->after('guru_id')->constrained('guru')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sesi_pembelajaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diisi_oleh_guru_id');
        });
    }
};
