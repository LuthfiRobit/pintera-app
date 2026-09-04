<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function buatSiswaDenganAkunDanPresensi(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan riwayat presensi (semua status) dalam rentang tanggal default (bulan ini)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesiHadir = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(1)]);
    $sesiSakit = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(2)]);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiHadir->id, 'status' => 'hadir']);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiSakit->id, 'status' => 'sakit', 'keterangan' => 'Demam']);

    $response = $this->actingAs($user)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    // Kalau ini gagal (list kosong padahal harus ada 2), berarti whereHas('sesiPembelajaran', ...) BUTUH
    // withoutGlobalScope(TenantScope::class) juga -- tambahkan di controller, JANGAN ubah assertion ini.
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 2);
});

it('tidak menampilkan riwayat di luar rentang tanggal filter', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesiLampau = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->subMonths(3)]);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiLampau->id, 'status' => 'izin']);

    $response = $this->actingAs($user)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 0);
});

it('regresi identitas: siswa A hanya melihat presensinya sendiri, bukan milik siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $siswaA] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    [$userB, $siswaB] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(1)]);
    Presensi::factory()->create(['siswa_id' => $siswaA->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'hadir']);
    Presensi::factory()->create(['siswa_id' => $siswaB->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'sakit']);

    $response = $this->actingAs($userA)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 1 && $list->first()->siswa_id === $siswaA->id);
});
