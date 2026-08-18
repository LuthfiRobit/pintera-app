<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

function siapkanGuruDenganJadwalHariIni(): array
{
    Carbon::setTestNow(Carbon::parse('2026-08-19')); // a Wednesday

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('guruUser', 'guru', 'kelas', 'jadwal', 'semester', 'siswa');
}

it('denies access without presensi.isi permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.sesi.index'))->assertForbidden();
});

it('auto-generates and lists today\'s sesi belonging to the logged-in guru', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();

    $response = $this->actingAs($guruUser)->get(route('guru.sesi.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 1);
});

it('does not show a sesi belonging to a different guru', function () {
    ['kelas' => $kelas] = siapkanGuruDenganJadwalHariIni();

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $lainRole = Role::firstOrCreate(['name' => 'guru_lain', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $lainRole->givePermissionTo(['presensi.isi']);
    $guruLainUser = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $guruLainUser->assignRole($lainRole);
    Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'user_id' => $guruLainUser->id]);

    $response = $this->actingAs($guruLainUser)->get(route('guru.sesi.index'));

    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 0);
});

it('saves jurnal materi and per-student presensi status', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.sesi.index')); // triggers generation
    $sesi = SesiPembelajaran::firstOrFail();

    $this->actingAs($guruUser)->put(route('guru.sesi.update', $sesi), [
        'materi' => 'Perkalian dan pembagian',
        'presensi' => [
            $siswa->id => 'izin',
        ],
    ])->assertRedirect(route('guru.sesi.index'));

    expect($sesi->fresh()->materi)->toBe('Perkalian dan pembagian');
    expect($sesi->fresh()->presensi()->where('siswa_id', $siswa->id)->first()->status->value)->toBe('izin');
});

it('forbids a guru from updating a sesi that does not belong to them', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.sesi.index')); // triggers generation
    $sesi = SesiPembelajaran::firstOrFail();

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $lainRole = Role::firstOrCreate(['name' => 'guru_lain2', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $lainRole->givePermissionTo(['presensi.isi']);
    $guruLainUser = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $guruLainUser->assignRole($lainRole);
    Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'user_id' => $guruLainUser->id]);

    $this->actingAs($guruLainUser)->put(route('guru.sesi.update', $sesi), [
        'materi' => 'Mencoba mengubah sesi orang lain',
        'presensi' => [],
    ])->assertForbidden();
});
