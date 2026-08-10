<?php
// database/migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lembagaList = DB::table('lembaga')->where('memungut_iuran', true)->get();

        foreach ($lembagaList as $lembaga) {
            $sudahAda = DB::table('jenis_tagihan')
                ->where('lembaga_id', $lembaga->id)
                ->where('kategori', 'spp')
                ->exists();

            if ($sudahAda) {
                continue;
            }

            DB::table('jenis_tagihan')->insert([
                'lembaga_id' => $lembaga->id,
                'nama' => $lembaga->periode_iuran === 'tahunan' ? 'SPP Tahunan' : 'SPP Bulanan',
                'kategori' => 'spp',
                'bisa_dicicil' => false,
                'maks_cicilan' => null,
                'priority_score' => null,
                'default_amount' => $lembaga->nominal_iuran,
                'mode' => 'otomatis',
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => null,
                'tanggal_generate' => 1,
                'hari_jatuh_tempo' => 10,
                'va_expire_hours' => null,
                'is_active' => true,
                'last_generated_period' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Destructive on rollback: removes every 'spp' jenis_tagihan, not just
     * the ones this migration created. Acceptable for a one-time historical
     * data migration in this dev/demo environment — rolling back this far
     * back in the migration history is not an expected operation once
     * Sub-project 2 (which lets admins create their own 'spp' jenis_tagihan
     * rows through the UI) has shipped.
     */
    public function down(): void
    {
        DB::table('jenis_tagihan')->where('kategori', 'spp')->delete();
    }
};
