<?php
// database/migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php

use App\Models\Pendaftaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time historical backfill: every tagihan row that predates the
     * polymorphic columns (added in 2026_08_10_130000) only has
     * pendaftaran_id set. All pre-existing tagihan rows are PPDB rows, so
     * tagihable is always Pendaftaran. Guarded by whereNull so re-running
     * this migration (e.g. via migrate:refresh) never overwrites rows that
     * were created after the polymorphic columns already existed.
     */
    public function up(): void
    {
        DB::table('tagihan')
            ->whereNull('tagihable_type')
            ->whereNotNull('pendaftaran_id')
            ->update(['tagihable_type' => Pendaftaran::class]);

        DB::statement(
            'UPDATE tagihan SET tagihable_id = pendaftaran_id WHERE tagihable_type = ? AND tagihable_id IS NULL',
            [Pendaftaran::class]
        );
    }

    public function down(): void
    {
        DB::table('tagihan')
            ->where('tagihable_type', Pendaftaran::class)
            ->update(['tagihable_type' => null, 'tagihable_id' => null]);
    }
};
