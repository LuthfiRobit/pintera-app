<?php

use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaAktifDiKelas(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    return compact('yayasan', 'lembaga', 'tahunAjaran', 'kelas', 'siswa');
}

it('snapshot kelas_id ke kelas_terakhir_id dan null-kan kelas_id saat transisi Aktif ke Keluar', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();

    (new UpdateStatusSiswaAction)->execute($siswa, StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelas->id);
});

it('memulihkan kelas_id dari kelas_terakhir_id dan mengosongkan kelas_terakhir_id saat kembali ke Aktif', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;
    $action->execute($siswa, StatusSiswa::Keluar);

    $action->execute($siswa->fresh(), StatusSiswa::Aktif);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
    expect($siswa->kelas_id)->toBe($kelas->id);
    expect($siswa->kelas_terakhir_id)->toBeNull();
});

it('siklus ganda Aktif->Keluar->Aktif->Keluar lagi mengambil snapshot kelas yang benar di siklus kedua', function () {
    ['siswa' => $siswa, 'kelas' => $kelasPertama, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;

    // Siklus 1: keluar dari kelas pertama, lalu aktif lagi (otomatis kembali ke kelas pertama).
    $action->execute($siswa, StatusSiswa::Keluar);
    $action->execute($siswa->fresh(), StatusSiswa::Aktif);

    // Siswa pindah ke kelas KEDUA saat aktif kembali (skenario realistis: admin assign ulang).
    $kelasKedua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa->fresh()->update(['kelas_id' => $kelasKedua->id]);

    // Siklus 2: keluar lagi -- snapshot HARUS kelas kedua, bukan sisa data basi dari siklus pertama.
    $action->execute($siswa->fresh(), StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelasKedua->id);
});

it('idempotent: memanggil dengan status target sama dengan status sekarang tidak mengubah kelas_id/kelas_terakhir_id', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;
    $action->execute($siswa, StatusSiswa::Keluar);
    $siswaSetelahKeluar = $siswa->fresh();

    $action->execute($siswaSetelahKeluar, StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelas->id);
});

it('transisi Aktif langsung memanggil execute dengan status Aktif tanpa perubahan sebelumnya juga idempotent', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();

    (new UpdateStatusSiswaAction)->execute($siswa, StatusSiswa::Aktif);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
    expect($siswa->kelas_id)->toBe($kelas->id);
    expect($siswa->kelas_terakhir_id)->toBeNull();
});
