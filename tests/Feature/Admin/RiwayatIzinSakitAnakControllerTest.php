<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function buatOrangTuaDenganAnakDanPresensi(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ayah']);

    return [$user->fresh(), $anak];
}

it('menampilkan riwayat izin/sakit anak dalam rentang tanggal default (bulan ini)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(2)]);
    Presensi::factory()->create(['siswa_id' => $anak->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'sakit', 'keterangan' => 'Demam']);

    $response = $this->actingAs($user)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 1);
});

it('tidak menampilkan riwayat di luar rentang tanggal filter', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    $sesiLampau = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->subMonths(3)]);
    Presensi::factory()->create(['siswa_id' => $anak->id, 'sesi_pembelajaran_id' => $sesiLampau->id, 'status' => 'izin']);

    $response = $this->actingAs($user)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 0);
});

it('menolak kebocoran riwayat anak orang tua lain lewat siswa_id (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});

it('menampilkan panel kosong ramah jika akun bukan orang tua atau belum punya anak', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.riwayat-izin-sakit-anak.index'));

    $response->assertOk();
    $response->assertViewHas('anakList', fn ($list) => $list->isEmpty());
    $response->assertViewHas('riwayatList', fn ($list) => $list->isEmpty());
});
