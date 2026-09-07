<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->foreignId('yayasan_id')->nullable()->after('id')->constrained('yayasan')->cascadeOnDelete();
        });

        $this->backfillYayasanId();

        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->unsignedBigInteger('yayasan_id')->nullable(false)->change();
            $table->unique(['yayasan_id', 'nama']);
        });
    }

    /**
     * Sama seperti backfill jenis_karyawan_master, tapi lewat guru_jabatan_tambahan (pivot) ->
     * guru.lembaga_id -> lembaga.yayasan_id, karena Guru tidak punya kolom yayasan_id langsung.
     */
    private function backfillYayasanId(): void
    {
        $jabatanIds = DB::table('jabatan_tambahan_master')->pluck('id');

        foreach ($jabatanIds as $jabatanId) {
            $yayasanIds = DB::table('guru_jabatan_tambahan')
                ->join('guru', 'guru_jabatan_tambahan.guru_id', '=', 'guru.id')
                ->join('lembaga', 'guru.lembaga_id', '=', 'lembaga.id')
                ->where('guru_jabatan_tambahan.jabatan_tambahan_master_id', $jabatanId)
                ->distinct()
                ->pluck('lembaga.yayasan_id')
                ->sort()
                ->values();

            if ($yayasanIds->isEmpty()) {
                DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->update(['yayasan_id' => 1]);

                continue;
            }

            $primaryYayasanId = $yayasanIds->first();
            DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->update(['yayasan_id' => $primaryYayasanId]);

            foreach ($yayasanIds->skip(1) as $otherYayasanId) {
                $original = DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->first();

                $cloneId = DB::table('jabatan_tambahan_master')->insertGetId([
                    'nama' => $original->nama,
                    'kelompok' => $original->kelompok,
                    'yayasan_id' => $otherYayasanId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lembagaIdsOtherYayasan = DB::table('lembaga')->where('yayasan_id', $otherYayasanId)->pluck('id');

                DB::table('guru_jabatan_tambahan')
                    ->join('guru', 'guru_jabatan_tambahan.guru_id', '=', 'guru.id')
                    ->where('guru_jabatan_tambahan.jabatan_tambahan_master_id', $jabatanId)
                    ->whereIn('guru.lembaga_id', $lembagaIdsOtherYayasan)
                    ->update(['guru_jabatan_tambahan.jabatan_tambahan_master_id' => $cloneId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->dropUnique(['yayasan_id', 'nama']);
            $table->dropForeign(['yayasan_id']);
            $table->dropColumn('yayasan_id');
        });
    }
};
