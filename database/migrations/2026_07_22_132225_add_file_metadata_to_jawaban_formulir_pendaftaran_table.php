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
        Schema::table('jawaban_formulir_pendaftaran', function (Blueprint $table) {
            // Only populated when the underlying formulir_field.field_type is 'file' —
            // 'nilai' already stores the stored file path for those rows; these three
            // columns just add the metadata DokumenPendaftaran already tracks for its
            // own uploads, so the Review page can show a real filename instead of a path.
            $table->string('nama_file_asli')->nullable()->after('nilai');
            $table->string('mime_type')->nullable()->after('nama_file_asli');
            $table->unsignedBigInteger('ukuran_bytes')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jawaban_formulir_pendaftaran', function (Blueprint $table) {
            $table->dropColumn(['nama_file_asli', 'mime_type', 'ukuran_bytes']);
        });
    }
};
