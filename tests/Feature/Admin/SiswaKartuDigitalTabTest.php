<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminDenganPermission(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    Permission::firstOrCreate(['name' => 'siswa.edit', 'guard_name' => 'web']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo('siswa.edit');

    return [$user, $siswa];
}

it('tab kartu digital tampil di halaman edit siswa', function () {
    [$admin, $siswa] = buatAdminDenganPermission();

    $response = $this->actingAs($admin)->get(route('admin.siswa.edit', $siswa));

    $response->assertOk();
    $response->assertSee('Kartu Digital');
});

it('admin bisa generate ulang kartu siswa dari tab admin', function () {
    [$admin, $siswa] = buatAdminDenganPermission();
    $kartuLama = KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama-admin', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.siswa.kartu-digital.generate-ulang', $siswa))->assertRedirect();

    expect(KartuSiswa::find($kartuLama->id)->kode)->not->toBe('kode-lama-admin');
});

it('admin bisa menonaktifkan kartu siswa dari tab admin', function () {
    [$admin, $siswa] = buatAdminDenganPermission();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-nonaktifkan', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.siswa.kartu-digital.nonaktifkan', $siswa))->assertRedirect();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->first()->is_active)->toBeFalse();
});
