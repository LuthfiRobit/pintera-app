<?php

use App\Domains\Akademik\Models\KartuSiswa;
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

function buatSiswaLoginUntukKartu(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$siswa, $user->fresh()];
}

it('membuat kartu QR otomatis saat siswa pertama kali buka halaman kartu saya', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();

    $this->actingAs($user)->get(route('admin.kartu-saya.index'))->assertOk();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
});

it('tidak membuat kartu baru kalau siswa sudah punya kartu aktif', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama', 'is_active' => true]);

    $this->actingAs($user)->get(route('admin.kartu-saya.index'))->assertOk();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
    expect(KartuSiswa::where('siswa_id', $siswa->id)->first()->kode)->toBe('kode-lama');
});

it('generate ulang mengganti kode kartu yang sama, bukan membuat baris baru', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();
    $kartuLama = KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama', 'is_active' => true]);

    $this->actingAs($user)->post(route('admin.kartu-saya.generate-ulang'))->assertRedirect();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
    $kartuBaru = KartuSiswa::find($kartuLama->id);
    expect($kartuBaru->kode)->not->toBe('kode-lama');
});
