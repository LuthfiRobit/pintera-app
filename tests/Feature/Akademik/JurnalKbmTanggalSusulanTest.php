<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
});

it('bisa lihat sesi kemarin lewat query param tanggal', function () {
    $guru = Guru::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $guru->lembaga_id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole('guru');

    $kemarin = now()->subDay();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $guru->lembaga_id, 'status_aktif' => true]);
    Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $guru->lembaga_id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $jadwal = JadwalPelajaran::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id]);
    $sesiKemarin = SesiPembelajaran::factory()->create([
        'jadwal_pelajaran_id' => $jadwal->id,
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'lembaga_id' => $guru->lembaga_id,
        'tanggal' => $kemarin->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index', ['tanggal' => $kemarin->toDateString()]));

    $response->assertOk();
    $sesiList = $response->viewData('sesiList');
    expect($sesiList->pluck('id'))->toContain($sesiKemarin->id);
});

it('menolak tanggal di masa depan', function () {
    $guru = Guru::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $guru->lembaga_id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole('guru');

    $besok = now()->addDay()->toDateString();

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index', ['tanggal' => $besok]));

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('error');
});
