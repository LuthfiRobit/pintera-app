<?php

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\EkstrakurikulerLembaga;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasUser(): array
{
    Permission::firstOrCreate(['name' => 'rapor.input-wali', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wali_kelas', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('rapor.input-wali');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'wali_kelas_guru_id' => $guru->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return [$guruUser, $lembaga, $siswa, $semester];
}

it('shows ekskul options from the lembaga master data on the catatan wali kelas form', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'pilihan', 'nama_ekskul' => 'Futsal']);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Pramuka');
    $response->assertSee('Futsal');
});

it('saves catatan wali kelas when the ekskul name matches the lembaga master data', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'peran' => 'Anggota']],
    ])->assertRedirect();

    expect(CatatanWaliKelas::where('siswa_id', $siswa->id)->first()->ekstrakurikuler)
        ->toBe([['nama' => 'Pramuka', 'peran' => 'Anggota']]);
});

it('rejects an ekskul name that is not registered in the lembaga master data', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Ekskul Fiktif Tidak Terdaftar', 'peran' => 'Anggota']],
    ])->assertSessionHasErrors('ekstrakurikuler.0.nama');

    expect(CatatanWaliKelas::where('siswa_id', $siswa->id)->exists())->toBeFalse();
});

it('rejects an ekskul name that belongs to a different lembaga (tenant isolation)', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    $lembagaLain = Lembaga::factory()->create();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembagaLain->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Ekskul Lembaga Lain']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Ekskul Lembaga Lain', 'peran' => 'Anggota']],
    ])->assertSessionHasErrors('ekstrakurikuler.0.nama');
});
