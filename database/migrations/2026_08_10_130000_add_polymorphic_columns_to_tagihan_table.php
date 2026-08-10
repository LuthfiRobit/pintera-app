<?php
// database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            // The pendaftaran_id foreign key relies on the (pendaftaran_id, kategori)
            // unique index to satisfy MySQL's "column must be leftmost in some index"
            // FK requirement. Add a plain index on pendaftaran_id first so MySQL has
            // an alternative index to use once the unique index is dropped below.
            $table->index('pendaftaran_id', 'idx_tagihan_pendaftaran_id');
            $table->dropUnique(['pendaftaran_id', 'kategori']);
        });

        DB::statement('ALTER TABLE tagihan MODIFY pendaftaran_id BIGINT UNSIGNED NULL');

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('tagihable_type')->nullable()->after('pendaftaran_id');
            $table->unsignedBigInteger('tagihable_id')->nullable()->after('tagihable_type');
            $table->foreignId('jenis_tagihan_id')->nullable()->after('tagihable_id')->constrained('jenis_tagihan')->nullOnDelete();
            $table->string('billing_period', 7)->nullable()->after('kategori');
            $table->string('source_trigger')->default('manual')->after('billing_period');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('total_tagihan');
            $table->string('discount_type')->nullable()->after('discount_amount');
            $table->decimal('net_amount', 12, 2)->nullable()->after('discount_type');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('net_amount');
            $table->foreignId('cancelled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');

            $table->index(['tagihable_type', 'tagihable_id']);
        });

        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'spp', 'tahunan', 'kegiatan', 'custom') NOT NULL");
        DB::statement("ALTER TABLE tagihan MODIFY status ENUM('belum_bayar', 'dicicil', 'lunas', 'sebagian', 'dibatalkan') NOT NULL DEFAULT 'belum_bayar'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tagihan MODIFY status ENUM('belum_bayar', 'dicicil', 'lunas') NOT NULL DEFAULT 'belum_bayar'");
        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang') NOT NULL");

        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropIndex(['tagihable_type', 'tagihable_id']);
            $table->dropConstrainedForeignId('jenis_tagihan_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['tagihable_type', 'tagihable_id', 'billing_period', 'source_trigger', 'discount_amount', 'discount_type', 'net_amount', 'paid_amount', 'cancelled_at', 'cancel_reason']);
        });

        DB::statement('ALTER TABLE tagihan MODIFY pendaftaran_id BIGINT UNSIGNED NOT NULL');

        Schema::table('tagihan', function (Blueprint $table) {
            $table->unique(['pendaftaran_id', 'kategori']);
            $table->dropIndex('idx_tagihan_pendaftaran_id');
        });
    }
};
