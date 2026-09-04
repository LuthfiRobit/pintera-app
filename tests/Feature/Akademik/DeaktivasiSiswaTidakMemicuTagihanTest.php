<?php

use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('deaktivasi siswa via UpdateStatusSiswaAction TIDAK memicu tagihan baru meski JenisTagihan tanpa kriteria sasaran spesifik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    // JenisTagihan generik tanpa sasaranGrup sama sekali -- sebelum fix, ini "cocok semua siswa".
    JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id,
        'is_active' => true,
        'kategori' => 'spp',
    ]);

    $tagihanSebelum = Tagihan::whereHasMorph('tagihable', [Siswa::class], fn ($q) => $q->where('id', $siswa->id))->count();

    app(UpdateStatusSiswaAction::class)->execute($siswa, StatusSiswa::Keluar);

    $tagihanSesudah = Tagihan::whereHasMorph('tagihable', [Siswa::class], fn ($q) => $q->where('id', $siswa->id))->count();

    expect($tagihanSesudah)->toBe($tagihanSebelum);
});
