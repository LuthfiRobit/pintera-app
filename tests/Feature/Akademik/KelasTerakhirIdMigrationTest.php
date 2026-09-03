<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('membackfill kelas_terakhir_id dan mengosongkan kelas_id untuk siswa non-aktif yang sudah ada sebelum migration', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    // Migration backfill sudah jalan (RefreshDatabase menjalankan SEMUA migration termasuk
    // migration baru ini). Untuk menguji efek backfill-nya sendiri, kita simulasikan kondisi
    // "data lama sebelum migration" dengan menonaktifkan constraint sementara, insert row
    // yang melanggar invariant, lalu jalankan ULANG isi backfill UPDATE-nya secara langsung.
    DB::statement('ALTER TABLE siswa DROP CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif');
    $siswaLama = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'status' => 'keluar',
    ]);
    DB::statement('UPDATE siswa SET kelas_terakhir_id = kelas_id, kelas_id = NULL WHERE status != \'aktif\' AND kelas_id IS NOT NULL');
    DB::statement('ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif CHECK (status = \'aktif\' OR kelas_id IS NULL)');

    $siswaLama->refresh();
    expect($siswaLama->kelas_id)->toBeNull();
    expect($siswaLama->kelas_terakhir_id)->toBe($kelas->id);
});

it('menolak insert siswa non-aktif dengan kelas_id terisi lewat query mentah (CHECK constraint aktif)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    expect(fn () => Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'status' => 'keluar',
    ]))->toThrow(QueryException::class);
});
