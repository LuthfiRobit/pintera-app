<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('komponen_penilaian', hasElemenCpColumn: true);
        $this->backfill('asesmen', hasElemenCpColumn: false);
    }

    private function backfill(string $table, bool $hasElemenCpColumn): void
    {
        $rows = DB::table($table)->whereNull('subjek_type')->get();

        foreach ($rows as $row) {
            $elemenCpKode = $hasElemenCpColumn ? ($row->elemen_cp ?? null) : null;

            if ($elemenCpKode !== null) {
                // Precedence: elemen_cp menang kalau terisi -- lebih bermakna
                // untuk PAUD daripada mata_pelajaran_id dummy.
                // Query builder murni (DB::table), BUKAN Eloquent model --
                // migration tidak boleh bergantung pada definisi model yang
                // bisa berubah di masa depan (global scope, cast, dst).
                $elemenCpId = DB::table('elemen_cp')->where('kode', $elemenCpKode)->value('id');

                if ($elemenCpId === null) {
                    throw new \RuntimeException(
                        "Backfill gagal: baris {$table}#{$row->id} punya elemen_cp='{$elemenCpKode}' yang tidak ditemukan di tabel elemen_cp."
                    );
                }

                DB::table($table)->where('id', $row->id)->update([
                    'subjek_type' => 'elemen_cp',
                    'subjek_id' => $elemenCpId,
                ]);

                continue;
            }

            if ($row->mata_pelajaran_id !== null) {
                DB::table($table)->where('id', $row->id)->update([
                    'subjek_type' => 'mata_pelajaran',
                    'subjek_id' => $row->mata_pelajaran_id,
                ]);

                continue;
            }

            // Tidak ada elemen_cp maupun mata_pelajaran_id -- baris tak
            // terpetakan. Fail keras, JANGAN silent skip.
            throw new \RuntimeException(
                "Backfill gagal: baris {$table}#{$row->id} tidak punya elemen_cp maupun mata_pelajaran_id -- tidak bisa dipetakan ke subjek manapun."
            );
        }
    }

    public function down(): void
    {
        DB::table('komponen_penilaian')->update(['subjek_type' => null, 'subjek_id' => null]);
        DB::table('asesmen')->update(['subjek_type' => null, 'subjek_id' => null]);
    }
};
