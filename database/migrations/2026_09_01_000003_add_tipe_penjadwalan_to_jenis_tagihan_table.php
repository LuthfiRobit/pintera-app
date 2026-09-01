<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->enum('tipe', ['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])->nullable()->after('kategori');
            $table->unsignedTinyInteger('hari_generate')->nullable()->after('tanggal_generate');
            $table->unsignedTinyInteger('bulan_generate')->nullable()->after('hari_generate');
            $table->unsignedSmallInteger('offset_hari_jatuh_tempo')->nullable()->after('hari_jatuh_tempo');
            $table->dropColumn('last_generated_period');
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('billing_period', 10)->nullable()->change();
        });

        DB::statement("UPDATE jenis_tagihan SET tipe = 'bulanan' WHERE mode = 'otomatis'");
        DB::statement("UPDATE jenis_tagihan SET tipe = 'sekali' WHERE mode = 'manual'");

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->enum('tipe', ['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])->nullable(false)->change();
        });

        DB::statement('ALTER TABLE jenis_tagihan ADD CONSTRAINT chk_jenis_tagihan_mode_tipe CHECK (NOT (mode = \'otomatis\' AND tipe = \'sekali\'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jenis_tagihan DROP CONSTRAINT chk_jenis_tagihan_mode_tipe');

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->dropColumn(['tipe', 'hari_generate', 'bulan_generate', 'offset_hari_jatuh_tempo']);
            $table->string('last_generated_period', 7)->nullable();
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('billing_period', 7)->nullable()->change();
        });
    }
};
