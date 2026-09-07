<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->foreignId('yayasan_id')->nullable()->after('id')->constrained('yayasan')->cascadeOnDelete();
        });

        $this->backfillYayasanId();

        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->unsignedBigInteger('yayasan_id')->nullable(false)->change();
            $table->unique(['yayasan_id', 'nama']);
        });
    }

    /**
     * Untuk tiap baris jenis_karyawan_master: cari yayasan mana saja yang memakainya lewat
     * karyawan.jenis_karyawan_id (Karyawan punya kolom yayasan_id langsung). Belum dipakai
     * sama sekali -> assign ke yayasan_id=1. Dipakai tepat 1 yayasan -> assign ke situ. Dipakai
     * lebih dari 1 yayasan (tidak terjadi di data sekarang, tapi harus ditangani) -> baris asli
     * ke yayasan id terkecil, baris lain di-clone dan karyawan.jenis_karyawan_id direpoint ke
     * clone masing-masing.
     */
    private function backfillYayasanId(): void
    {
        $jenisIds = DB::table('jenis_karyawan_master')->pluck('id');

        foreach ($jenisIds as $jenisId) {
            $yayasanIds = DB::table('karyawan')
                ->where('jenis_karyawan_id', $jenisId)
                ->whereNotNull('yayasan_id')
                ->distinct()
                ->pluck('yayasan_id')
                ->sort()
                ->values();

            if ($yayasanIds->isEmpty()) {
                DB::table('jenis_karyawan_master')->where('id', $jenisId)->update(['yayasan_id' => 1]);

                continue;
            }

            $primaryYayasanId = $yayasanIds->first();
            DB::table('jenis_karyawan_master')->where('id', $jenisId)->update(['yayasan_id' => $primaryYayasanId]);

            foreach ($yayasanIds->skip(1) as $otherYayasanId) {
                $original = DB::table('jenis_karyawan_master')->where('id', $jenisId)->first();

                $cloneId = DB::table('jenis_karyawan_master')->insertGetId([
                    'nama' => $original->nama,
                    'is_konselor' => $original->is_konselor,
                    'yayasan_id' => $otherYayasanId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('karyawan')
                    ->where('jenis_karyawan_id', $jenisId)
                    ->where('yayasan_id', $otherYayasanId)
                    ->update(['jenis_karyawan_id' => $cloneId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->dropUnique(['yayasan_id', 'nama']);
            $table->dropForeign(['yayasan_id']);
            $table->dropColumn('yayasan_id');
        });
    }
};
