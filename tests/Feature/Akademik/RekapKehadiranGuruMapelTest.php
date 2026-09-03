<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
});

it('guru mapel (bukan wali kelas) bisa lihat Rekap Kehadiran, difilter ke sesinya sendiri', function () {
    $kelas = Kelas::factory()->create();
    $guruWali = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $kelas->update(['wali_kelas_guru_id' => $guruWali->id]);

    $userMapel = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $personMapel = Person::factory()->create(['user_id' => $userMapel->id]);
    $guruMapel = Guru::factory()->create(['person_id' => $personMapel->id, 'lembaga_id' => $kelas->lembaga_id]);
    $userMapel->assignRole('guru');

    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id, 'lembaga_id' => $kelas->lembaga_id, 'status' => 'aktif']);

    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruMapel->id]);

    $sesiMapel = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruMapel->id]);
    $sesiWaliHadir = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruWali->id]);
    $sesiWaliIzin = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruWali->id]);

    Presensi::create(['sesi_pembelajaran_id' => $sesiMapel->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiWaliHadir->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiWaliIzin->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);

    $response = $this->actingAs($userMapel)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelas->id]));

    $response->assertOk();
    // Guru mapel cuma lihat 1 sesi miliknya (1 hadir), bukan total kelas (2 hadir + 1 izin)
    $rekap = $response->viewData('rekap');
    $barisSiswa = collect($rekap)->firstWhere('siswa_id', $siswa->id);
    expect($barisSiswa)->not->toBeNull();
    expect($barisSiswa['hadir'])->toBe(1);
    expect($barisSiswa['izin'])->toBe(0);
});

it('wali kelas tetap melihat rekap penuh lintas mata pelajaran (tidak difilter)', function () {
    $kelas = Kelas::factory()->create();
    $guruWali = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $kelas->update(['wali_kelas_guru_id' => $guruWali->id]);

    $userWali = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $personWali = Person::factory()->create(['user_id' => $userWali->id]);
    $guruWali->update(['person_id' => $personWali->id]);
    $userWali->assignRole('guru');

    $userMapel = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $personMapel = Person::factory()->create(['user_id' => $userMapel->id]);
    $guruMapel = Guru::factory()->create(['person_id' => $personMapel->id, 'lembaga_id' => $kelas->lembaga_id]);

    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id, 'lembaga_id' => $kelas->lembaga_id, 'status' => 'aktif']);

    $sesiMapel = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruMapel->id]);
    $sesiWaliHadir = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruWali->id]);
    $sesiWaliIzin = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruWali->id]);

    Presensi::create(['sesi_pembelajaran_id' => $sesiMapel->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiWaliHadir->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiWaliIzin->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);

    $response = $this->actingAs($userWali)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelas->id]));

    $response->assertOk();
    $rekap = $response->viewData('rekap');
    $barisSiswa = collect($rekap)->firstWhere('siswa_id', $siswa->id);
    expect($barisSiswa)->not->toBeNull();
    expect($barisSiswa['hadir'])->toBe(2);
    expect($barisSiswa['izin'])->toBe(1);
    expect($response->viewData('isWaliKelas'))->toBeTrue();
});
