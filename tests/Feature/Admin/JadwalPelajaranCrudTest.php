<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJadwalManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jadwal-pelajaran.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without jadwal-pelajaran.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.jadwal-pelajaran.index'))->assertForbidden();
});

it('creates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jam->id)->exists())->toBeTrue();
});

it('only offers is_pelajaran slots when creating a jadwal entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamBelajar = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranList', function ($list) use ($jamBelajar, $jamIstirahat) {
        return $list->contains('id', $jamBelajar->id) && ! $list->contains('id', $jamIstirahat->id);
    });
});

it('rejects a kelas_id belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});

it('rejects a guru_id belonging to another lembaga even when kelas_id is own', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruB->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('only lists semester and kelas belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
});

it('defaults to the active tahun ajaran when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasAktif->id));
});

it('rejects a jam_pelajaran_id belonging to a different pola jam than the kelas uses', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamLain->id,
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
